<?php

namespace App\Models;

use App\Feature;
use App\Models\Concerns\DibatasiProdi;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Krs extends Model
{
    use DibatasiProdi, SerializesDatesInAppTimezone;

    /**
     * Status mahasiswa yang boleh mengisi KRS.
     */
    public const STATUS_MAHASISWA_BOLEH_KRS = MahasiswaProfile::STATUS_AKTIF;

    protected $table = 'krs';

    protected $fillable = ['mahasiswa_id', 'kelas_id', 'nilai', 'nilai_angka', 'nilai_divalidasi_at', 'nilai_divalidasi_oleh', 'status'];

    protected function casts(): array
    {
        return ['nilai_angka' => 'float', 'nilai_divalidasi_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Tanpa pendadaran, status TA mengikuti nilai mata kuliah TA/Skripsi (lihat TugasAkhir::sinkronDariNilai).
        static::saved(function (self $krs): void {
            if (! Feature::aktif('pendadaran') && ($krs->wasChanged('nilai') || ($krs->wasRecentlyCreated && $krs->nilai !== null))) {
                if (MataKuliah::query()->whereKey(KelasKuliah::query()->whereKey($krs->kelas_id)->value('matkul_id'))->value('tugas_akhir')) {
                    TugasAkhir::sinkronDariNilai($krs->mahasiswa_id);
                }
            }
        });
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }

    /**
     * Nilai sudah divalidasi admin (Validasi Nilai): terkunci untuk dosen dan admin sampai validasinya dibatalkan.
     */
    public function nilaiTervalidasi(): bool
    {
        return $this->nilai_divalidasi_at !== null;
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nilai_divalidasi_oleh');
    }

    /**
     * Angka per komponen nilai (Nilai Semester).
     */
    public function nilaiKomponen(): HasMany
    {
        return $this->hasMany(NilaiKomponen::class);
    }

    /**
     * Prodi yang skala nilainya dipakai untuk KRS ini: prodi mata kuliahnya (lihat SkalaNilai::semua()).
     */
    public function prodiNilai(): ?int
    {
        $mataKuliah = $this->kelasKuliah?->mataKuliah;

        if ($mataKuliah === null) {
            return null;
        }

        // prodi_id wajib terisi; kosong berarti kolomnya tidak ikut dimuat, jadi ambil sekali per request.
        $id = $mataKuliah->id;

        return $mataKuliah->prodi_id ?? once(fn (): ?int => MataKuliah::query()->whereKey($id)->value('prodi_id'));
    }

    public function bobotNilai(): ?float
    {
        return SkalaNilai::bobot($this->nilai, $this->prodiNilai());
    }

    public function nilaiLulus(): bool
    {
        return SkalaNilai::lulus($this->nilai, $this->prodiNilai());
    }

    public function nilaiBolehDiulang(): bool
    {
        return SkalaNilai::bolehDiulang($this->nilai, $this->prodiNilai());
    }

    /**
     * KRS mata kuliah TA/Skripsi yang belum dinilai. Nilainya baru keluar dari pendadaran, jadi tidak ikut IPS.
     */
    public function taBelumDinilai(): bool
    {
        return blank($this->nilai) && (bool) $this->kelasKuliah?->mataKuliah?->tugas_akhir;
    }

    /**
     * TA/Skripsi semester lalu yang belum selesai: statusnya "Berlanjut" dan diambil lagi di KRS semester berikutnya.
     */
    public function taBerlanjut(): bool
    {
        return $this->taBelumDinilai() && $this->kelasKuliah?->tahunAkademik?->status === false;
    }

    /**
     * Hapus KRS beserta pengajuan pindah kelas yang masih menunggu untuk kelas ini.
     */
    public function cancel(): void
    {
        DB::transaction(function (): void {
            PengajuanPindahKelas::query()
                ->where('mahasiswa_id', $this->mahasiswa_id)
                ->where('kelas_asal_id', $this->kelas_id)
                ->where('status', PengajuanPindahKelas::STATUS_PENDING)
                ->delete();

            $this->delete();
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    public static function saringProdi(Builder $query, int $prodiId): void
    {
        $query->whereHas('kelasKuliah')->whereHas('mahasiswa');
    }

    public function milikProdi(int $prodiId): bool
    {
        return KelasKuliah::query()->whereKey($this->kelas_id)->exists() && MahasiswaProfile::query()->whereKey($this->mahasiswa_id)->exists();
    }
}
