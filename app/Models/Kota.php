<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kota extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'kotas';

    protected $fillable = ['provinsi_id', 'kode', 'nama'];

    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(Provinsi::class, 'provinsi_id');
    }
}
