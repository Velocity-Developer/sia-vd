<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengajuan tugas akhir, pendadaran, dan wisuda. Isian form berbeda per jenis dan disimpan di `isian`.
 *
 * Selama berstatus menunggu, mahasiswa tidak bisa membatalkan maupun mengisi form baru untuk jenis yang
 * sama. "Perlu perbaikan" membuka form yang sama untuk dikirim ulang; "ditolak" membuka form baru.
 */
class PengajuanAkademik extends Model
{
    use SerializesDatesInAppTimezone;

    public const TUGAS_AKHIR = 'tugas_akhir';

    public const PENDADARAN = 'pendadaran';

    public const WISUDA = 'wisuda';

    public const JENIS = [self::TUGAS_AKHIR, self::PENDADARAN, self::WISUDA];

    public const LABEL_JENIS = [
        self::TUGAS_AKHIR => 'Tugas Akhir',
        self::PENDADARAN => 'Pendadaran',
        self::WISUDA => 'Wisuda',
    ];

    public const MENUNGGU = 'menunggu';

    public const PERLU_PERBAIKAN = 'perlu_perbaikan';

    public const DISETUJUI = 'disetujui';

    public const DITOLAK = 'ditolak';

    public const STATUS = [self::MENUNGGU, self::PERLU_PERBAIKAN, self::DISETUJUI, self::DITOLAK];

    protected $table = 'pengajuan_akademik';

    protected $fillable = ['mahasiswa_id', 'jenis', 'isian', 'lampiran', 'status', 'catatan', 'diproses_oleh', 'diproses_at', 'diajukan_at'];

    protected function casts(): array
    {
        return [
            'isian' => 'array',
            'lampiran' => 'array',
            'diproses_at' => 'datetime',
            'diajukan_at' => 'datetime',
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

    /**
     * Ubah status dan catat ke riwayat. Status menunggu = dikirim (ulang) oleh mahasiswa.
     */
    public function catat(string $status, ?string $catatan, ?int $oleh): void
    {
        $diajukan = $status === self::MENUNGGU;

        $this->update([
            'status' => $status,
            'catatan' => $diajukan ? null : $catatan,
            'diproses_oleh' => $diajukan ? null : $oleh,
            'diproses_at' => $diajukan ? null : now(),
            'diajukan_at' => $diajukan ? now() : $this->diajukan_at,
        ]);

        $this->riwayat()->create(['status' => $status, 'catatan' => $catatan, 'oleh' => $oleh]);
    }
}
