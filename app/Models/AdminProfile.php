<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminProfile extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['user_id', 'foto', 'nomor_induk', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'no_telepon', 'alamat', 'kewarganegaraan', 'prodi_id'];

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date'];
    }

    /** Akun Prodi: program studi yang datanya boleh dikelola (lihat App\LingkupProdi). */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
