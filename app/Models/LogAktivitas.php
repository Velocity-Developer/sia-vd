<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris jejak aktivitas pengguna: data dibuat/diubah/dihapus, atau masuk/keluar. Dicatat otomatis oleh
 * App\CatatAktivitas; tidak pernah diubah.
 */
class LogAktivitas extends Model
{
    use SerializesDatesInAppTimezone;

    public const DIBUAT = 'dibuat';

    public const DIUBAH = 'diubah';

    public const DIHAPUS = 'dihapus';

    public const MASUK = 'masuk';

    public const KELUAR = 'keluar';

    public const AKSI = [
        self::DIBUAT => 'Dibuat',
        self::DIUBAH => 'Diubah',
        self::DIHAPUS => 'Dihapus',
        self::MASUK => 'Masuk',
        self::KELUAR => 'Keluar',
    ];

    public const UPDATED_AT = null;

    protected $table = 'log_aktivitas';

    protected $fillable = ['user_id', 'aksi', 'objek_tipe', 'objek_id', 'label', 'perubahan', 'ip', 'rute'];

    protected function casts(): array
    {
        return ['perubahan' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
