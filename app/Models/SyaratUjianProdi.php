<?php

namespace App\Models;

use App\Models\Concerns\DibatasiProdi;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Syarat ujian & remedial milik satu prodi (Akademik → Konfigurasi → Syarat Ujian & Remedial). Prodi yang belum
 * punya baris memakai pengaturan umum di PengaturanAkademik (lihat PengaturanAkademik::untukProdi()).
 */
class SyaratUjianProdi extends Model
{
    use DibatasiProdi, SerializesDatesInAppTimezone;

    /** Kolom yang sama dengan pengaturan umum di PengaturanAkademik. */
    public const KOLOM = ['syarat_ujian_aktif', 'min_kehadiran_ujian', 'izin_sakit_dihitung_hadir', 'huruf_maks_remidi'];

    protected $fillable = ['prodi_id', ...self::KOLOM];

    protected function casts(): array
    {
        return [
            'syarat_ujian_aktif' => 'boolean',
            'min_kehadiran_ujian' => 'integer',
            'izin_sakit_dihitung_hadir' => 'boolean',
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
