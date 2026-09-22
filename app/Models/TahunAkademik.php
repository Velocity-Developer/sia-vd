<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahunAkademik extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'tahun_akademik';

    protected $fillable = ['tahun', 'semester', 'tanggal_mulai', 'tanggal_akhir', 'tanggal_krs_awal', 'tanggal_krs_akhir', 'status'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_akhir' => 'date',
            'status' => 'boolean',
        ];
    }

    public function kelasKuliahs(): HasMany
    {
        return $this->hasMany(KelasKuliah::class, 'tahun_akademik_id');
    }
}
