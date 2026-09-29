<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengajuan tugas akhir, pendadaran, wisuda, cuti, dan aktif kembali. Isian form berbeda per jenis dan disimpan di `isian`.
 *
 * Selama berstatus menunggu, mahasiswa tidak bisa membatalkan maupun mengisi form baru untuk jenis yang
 * sama. "Perlu perbaikan" membuka form yang sama untuk dikirim ulang; "ditolak" membuka form baru.
 * Pendaftaran pendadaran menunggu salah satu pembimbing dulu (menunggu_pembimbing), lalu admin (menunggu).
 */
class PengajuanAkademik extends Model
{
    use SerializesDatesInAppTimezone;

    public const TUGAS_AKHIR = 'tugas_akhir';

    public const PENDADARAN = 'pendadaran';

    public const WISUDA = 'wisuda';

    /** Cuti satu semester (isian tahun_akademik_id + alasan, lampiran bukti bayar); lihat App\PengajuanCuti. */
    public const CUTI = 'cuti';

    /** Mahasiswa Cuti meminta status Aktif kembali. */
    public const AKTIF_KEMBALI = 'aktif_kembali';

    /** Jenis milik halaman Tugas Akhir & Wisuda. */
    public const JENIS = [self::TUGAS_AKHIR, self::PENDADARAN, self::WISUDA];

    /** Jenis milik halaman Pengajuan Cuti. */
    public const JENIS_CUTI = [self::CUTI, self::AKTIF_KEMBALI];

    public const LABEL_JENIS = [
        self::TUGAS_AKHIR => 'Tugas Akhir',
        self::PENDADARAN => 'Pendadaran',
        self::WISUDA => 'Wisuda',
        self::CUTI => 'Cuti',
        self::AKTIF_KEMBALI => 'Aktif Kembali',
    ];

    /** Menunggu keputusan admin. */
    public const MENUNGGU = 'menunggu';

    /** Pendadaran: menunggu persetujuan salah satu pembimbing sebelum naik ke admin. */
    public const MENUNGGU_PEMBIMBING = 'menunggu_pembimbing';

    /** Status yang mengunci form mahasiswa. */
    public const SEDANG_DIPROSES = [self::MENUNGGU_PEMBIMBING, self::MENUNGGU];

    /** Peristiwa di riwayat selain perubahan status biasa. */
    public const DIKIRIM = 'dikirim';

    public const DISETUJUI_PEMBIMBING = 'disetujui_pembimbing';

    public const PERLU_PERBAIKAN = 'perlu_perbaikan';

    public const DISETUJUI = 'disetujui';

    public const DITOLAK = 'ditolak';

    public const STATUS = [self::MENUNGGU_PEMBIMBING, self::MENUNGGU, self::PERLU_PERBAIKAN, self::DISETUJUI, self::DITOLAK];

    protected $table = 'pengajuan_akademik';

    protected $fillable = ['mahasiswa_id', 'jenis', 'tugas_akhir_id', 'isian', 'lampiran', 'status', 'catatan', 'diproses_oleh', 'diproses_at', 'diajukan_at', 'disetujui_pembimbing_oleh', 'disetujui_pembimbing_at'];

    protected function casts(): array
    {
        return [
            'isian' => 'array',
            'lampiran' => 'array',
            'diproses_at' => 'datetime',
            'diajukan_at' => 'datetime',
            'disetujui_pembimbing_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class);
    }

    public function pembimbingPenyetuju(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'disetujui_pembimbing_oleh');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatPengajuanAkademik::class)->oldest('id');
    }

    /**
     * Pengajuan terakhir mahasiswa untuk satu jenis; menentukan keadaan form.
     */
    public static function terakhir(int $mahasiswaId, string $jenis): ?self
    {
        return static::query()->where('mahasiswa_id', $mahasiswaId)->where('jenis', $jenis)->latest('id')->first();
    }

    public function menunggu(): bool
    {
        return $this->status === self::MENUNGGU;
    }

    public function sedangDiproses(): bool
    {
        return in_array($this->status, self::SEDANG_DIPROSES, true);
    }

    /**
     * Mahasiswa mengirim (ulang) pengajuan. Pendadaran ke pembimbing dulu, kecuali pembimbing sudah
     * menyetujuinya dan yang meminta perbaikan adalah admin.
     */
    public function kirim(int $oleh): void
    {
        $status = $this->jenis === self::PENDADARAN && $this->disetujui_pembimbing_at === null ? self::MENUNGGU_PEMBIMBING : self::MENUNGGU;

        $this->update(['status' => $status, 'catatan' => null, 'diproses_oleh' => null, 'diproses_at' => null, 'diajukan_at' => now()]);
        $this->riwayat()->create(['status' => self::DIKIRIM, 'oleh' => $oleh]);
    }

    /**
     * Ubah status oleh pemroses (pembimbing/admin) dan catat ke riwayat.
     */
    public function catat(string $status, ?string $catatan, int $oleh, ?string $peristiwa = null): void
    {
        $this->update(['status' => $status, 'catatan' => $catatan, 'diproses_oleh' => $oleh, 'diproses_at' => now()]);
        $this->riwayat()->create(['status' => $peristiwa ?? $status, 'catatan' => $catatan, 'oleh' => $oleh]);
    }
}
