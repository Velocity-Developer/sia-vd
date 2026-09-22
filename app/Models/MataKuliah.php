<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataKuliah extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'mata_kuliahs';

    protected $fillable = ['kode_matkul', 'nama_matkul', 'sks', 'semester', 'jenis', 'prodi_id'];

    protected function casts(): array
    {
        return [
            'sks' => 'integer',
            'semester' => 'integer',
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
}
