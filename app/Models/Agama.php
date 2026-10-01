<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;

class Agama extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'agamas';

    protected $fillable = ['kode', 'nama'];
}
