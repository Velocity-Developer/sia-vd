<?php

namespace App\Models;

use App\Models\Concerns\DibatasiProdi;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu tingkatan batas SKS milik prodi (Akademik → Konfigurasi → Batas SKS). Kolomnya sama dengan BatasSks global.
 */
class BatasSksProdi extends Model
{
    use DibatasiProdi, SerializesDatesInAppTimezone;

    protected $fillable = ['prodi_id', 'ips_minimal', 'maks_sks'];

    protected function casts(): array
    {
        return [
            'ips_minimal' => 'float',
            'maks_sks' => 'integer',
        ];
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public static function saringProdi(Builder $query, int $prodiId): void
    {
        $query->where($query->qualifyColumn('prodi_id'), $prodiId);
    }

    public function milikProdi(int $prodiId): bool
    {
        return (int) $this->prodi_id === $prodiId;
    }
}
