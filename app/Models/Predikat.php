<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;

/**
 * Predikat kelulusan untuk rentang IPK (bobot minimal–maksimal), diatur admin di Akademik → Konfigurasi → Predikat.
 */
class Predikat extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['nama', 'bobot_minimal', 'bobot_maksimal'];

    protected function casts(): array
    {
        return [
            'bobot_minimal' => 'float',
            'bobot_maksimal' => 'float',
        ];
    }

    /**
     * Nama predikat yang rentangnya memuat IPK, atau null bila IPK jatuh di luar semua rentang.
     */
    public static function untuk(float $ipk): ?string
    {
        $ipk = round($ipk, 2);

        return static::query()
            ->where('bobot_minimal', '<=', $ipk)
            ->where('bobot_maksimal', '>=', $ipk)
            ->orderByDesc('bobot_minimal')
            ->value('nama');
    }
}
