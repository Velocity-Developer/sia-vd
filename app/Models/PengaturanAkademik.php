<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanAkademik extends Model
{
    use SerializesDatesInAppTimezone;

    /**
     * Baris singleton yang selalu dipakai.
     */
    public const SINGLETON_ID = 1;

    protected $table = 'pengaturan_akademik';

    protected $fillable = ['maks_sks_tanpa_ips', 'kunci_krs_aktif', 'jumlah_pertemuan', 'min_kehadiran_ujian', 'toleransi_terlambat_menit', 'durasi_presensi_mandiri_menit', 'batas_pengajuan_izin_hari', 'syarat_ujian_aktif', 'huruf_maks_remidi', 'batas_pengajuan_susulan_hari', 'batas_bayar_susulan_hari', 'min_sks_pendadaran', 'updated_by'];

    protected $attributes = [
        'id' => self::SINGLETON_ID,
        'maks_sks_tanpa_ips' => 20,
        'kunci_krs_aktif' => false,
        'jumlah_pertemuan' => 16,
        'min_kehadiran_ujian' => 75,
        'toleransi_terlambat_menit' => 15,
        'durasi_presensi_mandiri_menit' => 15,
        'batas_pengajuan_izin_hari' => 1,
        'syarat_ujian_aktif' => false,
        'batas_pengajuan_susulan_hari' => 3,
        'batas_bayar_susulan_hari' => 3,
        'min_sks_pendadaran' => 138,
    ];

    protected function casts(): array
    {
        return [
            'maks_sks_tanpa_ips' => 'integer',
            'kunci_krs_aktif' => 'boolean',
            'jumlah_pertemuan' => 'integer',
            'min_kehadiran_ujian' => 'integer',
            'toleransi_terlambat_menit' => 'integer',
            'durasi_presensi_mandiri_menit' => 'integer',
            'batas_pengajuan_izin_hari' => 'integer',
            'syarat_ujian_aktif' => 'boolean',
            'batas_pengajuan_susulan_hari' => 'integer',
            'batas_bayar_susulan_hari' => 'integer',
            'min_sks_pendadaran' => 'integer',
        ];
    }

    /**
     * Ambil baris pengaturan, buat bila belum ada.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => self::SINGLETON_ID]);
    }

    /**
     * Batas SKS untuk IPS semester sebelumnya; null berarti belum ada IPS (mis. mahasiswa baru).
     */
    public static function maksSksUntuk(?float $ips): int
    {
        if ($ips === null) {
            return static::current()->maks_sks_tanpa_ips;
        }

        $tingkat = BatasSks::query()
            ->where('ips_minimal', '<=', round($ips, 2))
            ->orderByDesc('ips_minimal')
            ->first();

        return $tingkat?->maks_sks ?? static::current()->maks_sks_tanpa_ips;
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
