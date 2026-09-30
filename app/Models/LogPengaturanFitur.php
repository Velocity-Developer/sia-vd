<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu perubahan override fitur. Nilai null berarti tanpa override (ikut bawaan config).
 */
class LogPengaturanFitur extends Model
{
    use SerializesDatesInAppTimezone;

    public const UPDATED_AT = null;

    protected $table = 'log_pengaturan_fitur';

    protected $fillable = ['nama', 'aktif_lama', 'aktif_baru', 'diubah_oleh'];

    protected function casts(): array
    {
        return [
            'aktif_lama' => 'boolean',
            'aktif_baru' => 'boolean',
        ];
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
