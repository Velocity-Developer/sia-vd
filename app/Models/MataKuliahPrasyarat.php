<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pasangan prasyarat: mata kuliah hanya bisa diambil di KRS bila prasyaratnya sudah lulus.
 * Dikelola di menu Mata Kuliah Prasyarat dan juga lewat form Mata Kuliah (relasi MataKuliah::prasyarat()).
 */
class MataKuliahPrasyarat extends Model
{
    protected $table = 'mata_kuliah_prasyarat';

    public $timestamps = false;

    protected $fillable = ['mata_kuliah_id', 'prasyarat_id'];

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function prasyarat(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'prasyarat_id');
    }
}
