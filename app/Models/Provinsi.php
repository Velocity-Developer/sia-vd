<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provinsi extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'provinsis';

    protected $fillable = ['kode', 'nama'];

    public function kotas(): HasMany
    {
        return $this->hasMany(Kota::class, 'provinsi_id');
    }
}
