<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Skala nilai huruf (diatur admin di Pengaturan Akademik) untuk KRS, KHS, dan transkrip.
 */
class SkalaNilai extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['huruf', 'bobot', 'lulus', 'boleh_diulang'];

    protected function casts(): array
    {
        return [
            'bobot' => 'float',
            'lulus' => 'boolean',
            'boleh_diulang' => 'boolean',
        ];
    }

    /**
     * Seluruh skala, dimuat sekali per request.
     *
     * @return Collection<string, self>
     */
    public static function semua(): Collection
    {
        return once(fn (): Collection => static::query()->orderByDesc('bobot')->orderBy('huruf')->get()->keyBy('huruf'));
    }

    /**
     * @return list<string>
     */
    public static function huruf(): array
    {
        return static::semua()->keys()->all();
    }

    public static function bobot(?string $nilai): ?float
    {
        return static::semua()->get(strtoupper((string) $nilai))?->bobot;
    }

    public static function lulus(?string $nilai): bool
    {
        return (bool) static::semua()->get(strtoupper((string) $nilai))?->lulus;
    }

    public static function bolehDiulang(?string $nilai): bool
    {
        return (bool) static::semua()->get(strtoupper((string) $nilai))?->boleh_diulang;
    }
}
