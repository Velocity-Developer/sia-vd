<?php

namespace App\Models;

use App\Models\Concerns\DibatasiProdi;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bobot nilai huruf per prodi (diatur admin di Akademik → Konfigurasi → Bobot Nilai). Kolomnya sama dengan SkalaNilai.
 */
class BobotNilai extends Model
{
    use DibatasiProdi, SerializesDatesInAppTimezone;

    protected $fillable = ['prodi_id', 'huruf', 'bobot', 'angka_minimal', 'lulus', 'boleh_diulang'];

    protected function casts(): array
    {
        return [
            'bobot' => 'float',
            'angka_minimal' => 'float',
            'lulus' => 'boolean',
            'boleh_diulang' => 'boolean',
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
