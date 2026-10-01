<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadanHukum extends Model
{
    use SerializesDatesInAppTimezone;

    /**
     * Baris singleton yang selalu dipakai.
     */
    public const SINGLETON_ID = 1;

    // Kolom id bukan auto-increment: tanpa ini, simpanan pertama di MySQL memakai lastInsertId (0) dan
    // update berikutnya pada model yang sama tidak mengenai baris mana pun.
    public $incrementing = false;

    protected $table = 'badan_hukum';

    protected $fillable = [
        'nama_badan_hukum', 'tanggal_berdiri', 'nomor_akta_terakhir', 'tanggal_akta_terakhir', 'nomor_pengesahan',
        'tanggal_pengesahan', 'alamat_jalan', 'provinsi_id', 'kota_id', 'kode_pos', 'telepon', 'faximili', 'email',
        'website', 'updated_by',
    ];

    protected $attributes = [
        'id' => self::SINGLETON_ID,
    ];

    protected function casts(): array
    {
        return [
            'tanggal_berdiri' => 'date:Y-m-d',
            'tanggal_akta_terakhir' => 'date:Y-m-d',
            'tanggal_pengesahan' => 'date:Y-m-d',
        ];
    }

    /**
     * Ambil baris badan hukum, buat (kosong) bila belum ada.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => self::SINGLETON_ID]);
    }

    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(Provinsi::class, 'provinsi_id');
    }

    public function kota(): BelongsTo
    {
        return $this->belongsTo(Kota::class, 'kota_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
