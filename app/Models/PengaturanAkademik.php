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

    protected $fillable = ['maks_sks_tanpa_ips', 'updated_by'];

    protected $attributes = [
        'id' => self::SINGLETON_ID,
        'maks_sks_tanpa_ips' => 20,
    ];

    protected function casts(): array
    {
        return [
            'maks_sks_tanpa_ips' => 'integer',
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
