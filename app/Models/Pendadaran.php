<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Jadwal pendadaran (sidang akhir) yang ditetapkan admin saat menyetujui pendaftaran: ruang dan tiga penguji.
 *
 * Setiap penguji memberi nilai 0–100; ketua penguji menetapkan hasil dari rata-ratanya. Lulus langsung
 * menyelesaikan TA, lulus dengan revisi menunggu revisi disahkan ketua, tidak lulus membuka pendaftaran ulang.
 */
class Pendadaran extends Model
{
    use SerializesDatesInAppTimezone;

    public const DIJADWALKAN = 'dijadwalkan';

    public const REVISI = 'revisi';

    public const SELESAI = 'selesai';

    public const TIDAK_LULUS = 'tidak_lulus';

    /** Pendadaran yang masih berjalan untuk TA-nya (belum selesai/tidak lulus). */
    public const AKTIF = [self::DIJADWALKAN, self::REVISI];

    public const HASIL_LULUS = 'lulus';

    public const HASIL_LULUS_REVISI = 'lulus_revisi';

    public const HASIL_TIDAK_LULUS = 'tidak_lulus';

    public const HASIL = [self::HASIL_LULUS, self::HASIL_LULUS_REVISI, self::HASIL_TIDAK_LULUS];

    public const PERAN_PENGUJI = [1 => 'Ketua Penguji', 2 => 'Penguji 2', 3 => 'Penguji 3'];

    protected $table = 'pendadaran';

    protected $fillable = ['pengajuan_id', 'tugas_akhir_id', 'mahasiswa_id', 'nomor_surat', 'tanggal', 'jam_mulai', 'jam_akhir', 'ruang_id', 'penguji_1_id', 'penguji_2_id', 'penguji_3_id', 'status', 'dijadwalkan_oleh',
        'nilai_akhir', 'huruf', 'hasil', 'catatan_hasil', 'hasil_ditetapkan_at', 'naskah_revisi', 'revisi_diunggah_at', 'catatan_revisi', 'revisi_disahkan_at'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'nilai_akhir' => 'float',
            'hasil_ditetapkan_at' => 'datetime',
            'revisi_diunggah_at' => 'datetime',
            'revisi_disahkan_at' => 'datetime',
        ];
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(NilaiPendadaran::class)->orderBy('penguji_ke');
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanAkademik::class, 'pengajuan_id');
    }

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    /**
     * Prodi yang skala nilainya dipakai mengubah nilai angka pendadaran menjadi huruf: prodi mahasiswanya.
     */
    public function prodiNilai(): ?int
    {
        return $this->mahasiswa?->prodi_id;
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class);
    }

    public function penguji1(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'penguji_1_id');
    }

    public function penguji2(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'penguji_2_id');
    }

    public function penguji3(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'penguji_3_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDiuji(Builder $query, int $dosenId): void
    {
        $query->where(fn (Builder $q) => $q->where('penguji_1_id', $dosenId)->orWhere('penguji_2_id', $dosenId)->orWhere('penguji_3_id', $dosenId));
    }

    /**
     * @return array<int, int> nomor penguji => id dosen
     */
    public function pengujiIds(): array
    {
        return [1 => $this->penguji_1_id, 2 => $this->penguji_2_id, 3 => $this->penguji_3_id];
    }

    /**
     * Peran dosen di pendadaran ini (ketua/penguji), atau null bila bukan penguji.
     */
    public function peranPenguji(int $dosenId): ?string
    {
        $nomor = array_search($dosenId, $this->pengujiIds(), true);

        return $nomor === false ? null : self::PERAN_PENGUJI[$nomor];
    }

    /**
     * @return list<array{peran: string, nama: ?string}>
     */
    public function daftarPenguji(): array
    {
        return [
            ['peran' => self::PERAN_PENGUJI[1], 'nama' => $this->penguji1?->user?->name],
            ['peran' => self::PERAN_PENGUJI[2], 'nama' => $this->penguji2?->user?->name],
            ['peran' => self::PERAN_PENGUJI[3], 'nama' => $this->penguji3?->user?->name],
        ];
    }

    /**
     * Surat dan naskah revisi: mahasiswa pemilik, admin pemroses, pembimbing TA, dan penguji.
     */
    public function bolehDilihat(User $user): bool
    {
        if ($user->mahasiswaProfile !== null && $user->mahasiswaProfile->id === $this->mahasiswa_id) {
            return true;
        }
        if ($user->hasPermission('admin.pengajuan-akademik')) {
            return true;
        }
        $dosenId = $user->dosenProfile?->id;

        return $dosenId !== null && $user->hasPermission('dosen.bimbingan')
            && ($this->peranPenguji($dosenId) !== null || $this->tugasAkhir?->dibimbingOleh($dosenId));
    }

    public function waktuMulai(): Carbon
    {
        return $this->tanggal->copy()->setTimeFromTimeString($this->jam_mulai);
    }

    /**
     * Penguji boleh mengisi/mengubah nilai sejak pendadaran dimulai sampai hasil ditetapkan.
     */
    public function bolehDinilai(): bool
    {
        return $this->status === self::DIJADWALKAN && now()->greaterThanOrEqualTo($this->waktuMulai());
    }

    /**
     * Rata-rata nilai ketiga penguji; null selama belum semua penguji menilai.
     */
    public function rataRata(): ?float
    {
        $nilai = $this->relationLoaded('nilai') ? $this->nilai : $this->nilai()->get();

        return $nilai->count() === 3 ? round($nilai->avg('nilai'), 2) : null;
    }

    /**
     * Nomor surat pendadaran berurutan per tahun, mis. 007/PDD/X/2026.
     */
    public static function nomorSuratBaru(Carbon $tanggal): string
    {
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$tanggal->month - 1];
        $urut = static::query()->where('nomor_surat', 'like', "%/{$tanggal->year}")->count() + 1;

        return sprintf('%03d/PDD/%s/%d', $urut, $romawi, $tanggal->year);
    }

    /**
     * Tutup pendadaran yang lulus: TA selesai dan huruf pendadaran menjadi nilai akhir mata kuliah TA/Skripsi.
     */
    public function selesaikan(): void
    {
        DB::transaction(function (): void {
            $this->update(['status' => self::SELESAI]);
            $this->tugasAkhir->update(['status' => TugasAkhir::SELESAI, 'selesai_at' => now()]);

            // KRS mata kuliah TA yang belum dinilai lebih dulu, lalu yang terbaru.
            Krs::query()
                ->where('mahasiswa_id', $this->mahasiswa_id)
                ->whereHas('kelasKuliah.mataKuliah', fn (Builder $q) => $q->where('tugas_akhir', true))
                ->orderByRaw('nilai is null desc')
                ->latest('id')
                ->first()
                ?->update(['nilai' => $this->huruf]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function ringkasanHasil(): array
    {
        return [
            'nilai_akhir' => $this->nilai_akhir,
            'huruf' => $this->huruf,
            'hasil' => $this->hasil,
            'catatan_hasil' => $this->catatan_hasil,
            'hasil_ditetapkan_at' => $this->hasil_ditetapkan_at?->toIso8601String(),
            'ada_revisi' => $this->naskah_revisi !== null,
            'revisi_diunggah_at' => $this->revisi_diunggah_at?->toIso8601String(),
            'catatan_revisi' => $this->catatan_revisi,
            'revisi_disahkan_at' => $this->revisi_disahkan_at?->toIso8601String(),
        ];
    }

    /**
     * Ringkasan jadwal untuk ditampilkan ke mahasiswa/dosen/admin.
     *
     * @return array<string, mixed>
     */
    public function jadwal(): array
    {
        return [
            'id' => $this->id,
            'tanggal' => $this->tanggal->toDateString(),
            'jam_mulai' => substr($this->jam_mulai, 0, 5),
            'jam_akhir' => substr($this->jam_akhir, 0, 5),
            'ruang' => $this->ruang ? trim($this->ruang->kode_ruang.' '.$this->ruang->nama_ruang) : null,
            'penguji' => $this->daftarPenguji(),
            'status' => $this->status,
            'nomor_surat' => $this->nomor_surat,
        ];
    }
}
