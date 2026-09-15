<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengumpulanTugas extends Model
{
    protected $table = 'pengumpulan_tugas';

    protected $fillable = ['tugas_id', 'mahasiswa_id', 'file_jawaban', 'nilai', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'file_jawaban' => 'array',
            'nilai' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class);
    }
}
