<?php

namespace App\Models;

use App\Models\Concerns\DibatasiProdi;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Kurikulum program studi (Akademik → Konfigurasi → Kurikulum): mata kuliah prodi beserta semester dan sifat
 * wajib/pilihannya di kurikulum itu. MK TA/Skripsi dan PPL didaftarkan di sini seperti mata kuliah biasa.
 */
class Kurikulum extends Model
{
    use DibatasiProdi, SerializesDatesInAppTimezone;

    public const JENIS = ['Wajib', 'Pilihan'];

    protected $fillable = ['prodi_id', 'nama', 'tahun_akademik_id', 'aktif', 'keterangan'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function mataKuliahs(): BelongsToMany
    {
        return $this->belongsToMany(MataKuliah::class, 'kurikulum_mata_kuliah')->withPivot(['id', 'semester', 'jenis'])->withTimestamps();
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
