<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Krs extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'krs';

    protected $fillable = ['mahasiswa_id', 'kelas_id', 'nilai', 'status'];

    protected function casts(): array
    {
        return [];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }
}
