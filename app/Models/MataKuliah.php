<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataKuliah extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'mata_kuliahs';

    protected $fillable = ['kode_matkul', 'nama_matkul', 'sks', 'semester', 'jenis', 'tugas_akhir', 'prodi_id'];

    protected function casts(): array
    {
        return [
            'sks' => 'integer',
            'semester' => 'integer',
            'tugas_akhir' => 'boolean',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    public function kelasKuliah(): HasMany
    {
        return $this->hasMany(KelasKuliah::class, 'matkul_id');
    }

    /**
     * Mata kuliah yang harus lulus sebelum mata kuliah ini bisa diambil.
     */
    public function prasyarat(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'mata_kuliah_prasyarat', 'mata_kuliah_id', 'prasyarat_id');
    }

    /**
     * Mata kuliah yang mensyaratkan mata kuliah ini.
     */
    public function menjadiPrasyarat(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'mata_kuliah_prasyarat', 'prasyarat_id', 'mata_kuliah_id');
    }
}
