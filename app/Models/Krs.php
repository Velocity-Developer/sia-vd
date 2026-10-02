<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Krs extends Model
{
    use SerializesDatesInAppTimezone;

    /**
     * Status mahasiswa yang boleh mengisi KRS.
     */
    public const STATUS_MAHASISWA_BOLEH_KRS = MahasiswaProfile::STATUS_AKTIF;

    protected $table = 'krs';

    protected $fillable = ['mahasiswa_id', 'kelas_id', 'nilai', 'status'];

    protected function casts(): array
    {
        return [];
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
}
