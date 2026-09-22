<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu tingkatan batas SKS: mahasiswa dengan IPS semester sebelumnya >= ips_minimal boleh mengambil maks_sks.
 */
class BatasSks extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'batas_sks';

    protected $fillable = ['ips_minimal', 'maks_sks'];

    protected function casts(): array
    {
        return [
            'ips_minimal' => 'float',
            'maks_sks' => 'integer',
        ];
    }
}
