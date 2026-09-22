<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasKuliah extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'kelas_kuliah';

    protected $fillable = ['kode_kelas', 'tahun_akademik_id', 'kapasitas', 'dosen_id', 'matkul_id'];

    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
        ];
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'dosen_id');
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'matkul_id');
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'kelas_id');
    }

    public function materis(): HasMany
    {
        return $this->hasMany(Materi::class, 'kelas_id');
    }

    public function tugas(): HasMany
    {
        return $this->hasMany(Tugas::class, 'kelas_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'kelas_id');
    }

    public function krs(): HasMany
    {
        return $this->hasMany(Krs::class, 'kelas_id');
    }
}
