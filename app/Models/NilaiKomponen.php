<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Angka 0–100 satu komponen nilai untuk satu KRS, diisi di Nilai Semester.
 */
class NilaiKomponen extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['krs_id', 'komponen_nilai_id', 'nilai'];

    protected function casts(): array
    {
        return ['nilai' => 'float'];
    }

    public function krs(): BelongsTo
    {
        return $this->belongsTo(Krs::class);
    }

    public function komponen(): BelongsTo
    {
        return $this->belongsTo(KomponenNilai::class, 'komponen_nilai_id');
    }
}
