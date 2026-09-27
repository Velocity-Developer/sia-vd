<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;

class NilaiPendadaran extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'nilai_pendadaran';

    protected $fillable = ['pendadaran_id', 'penguji_ke', 'dosen_id', 'nilai', 'catatan'];

    protected function casts(): array
    {
        return ['nilai' => 'float', 'penguji_ke' => 'integer'];
    }
}
