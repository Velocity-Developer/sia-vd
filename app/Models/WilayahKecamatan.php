<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Kecamatan berkode Feeder PDDIKTI; nama sudah memuat kabupaten/kota dan provinsinya.
class WilayahKecamatan extends Model
{
    protected $table = 'wilayah_kecamatan';

    public $timestamps = false;

    protected $fillable = ['kode', 'nama'];
}
