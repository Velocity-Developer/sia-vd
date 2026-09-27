<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPengajuanAkademik extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'riwayat_pengajuan_akademik';

    protected $fillable = ['pengajuan_akademik_id', 'status', 'catatan', 'oleh'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
