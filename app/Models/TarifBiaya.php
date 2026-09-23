<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifBiaya extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'tarif_biaya';

    protected $fillable = ['jenis_biaya_id', 'prodi_id', 'angkatan', 'nominal'];

    protected function casts(): array
    {
        return ['angkatan' => 'integer', 'nominal' => 'integer'];
    }

    public function jenisBiaya(): BelongsTo
    {
        return $this->belongsTo(JenisBiaya::class, 'jenis_biaya_id');
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }
}
