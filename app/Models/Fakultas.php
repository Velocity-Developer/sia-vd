<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fakultas extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['kode_fakultas', 'nama_fakultas', 'dekan_id', 'tanggal_berdiri', 'no_telp', 'email'];

    protected function casts(): array
    {
        return ['tanggal_berdiri' => 'date'];
    }

    public function dekan(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'dekan_id');
    }

    public function programStudis(): HasMany
    {
        return $this->hasMany(ProgramStudi::class);
    }
}
