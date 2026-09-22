<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruang extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'ruangs';

    protected $fillable = ['kode_ruang', 'nama_ruang', 'kapasitas', 'detail'];

    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
        ];
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'ruang_id');
    }
}
