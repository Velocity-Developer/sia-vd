<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mahasiswa yang masuk daftar remidi satu kelas (remidi per mata kuliah, sekali).
 */
class RemidiPeserta extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'remidi_pesertas';

    protected $fillable = ['kelas_id', 'mahasiswa_id', 'nilai_awal', 'diusulkan'];

    protected function casts(): array
    {
        return [
            'diusulkan' => 'boolean',
        ];
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }
}
