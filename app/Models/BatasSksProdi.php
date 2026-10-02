<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu tingkatan batas SKS milik prodi (Akademik → Konfigurasi → Batas SKS). Kolomnya sama dengan BatasSks global.
 */
class BatasSksProdi extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['prodi_id', 'ips_minimal', 'maks_sks'];

    protected function casts(): array
    {
        return [
            'ips_minimal' => 'float',
            'maks_sks' => 'integer',
        ];
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }
}
