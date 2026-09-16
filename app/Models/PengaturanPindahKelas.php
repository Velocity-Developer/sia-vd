<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanPindahKelas extends Model
{
    /**
     * Baris singleton yang selalu dipakai.
     */
    public const SINGLETON_ID = 1;

    protected $table = 'pengaturan_pindah_kelas';

    protected $fillable = ['is_active', 'updated_by'];

    protected $attributes = [
        'id' => self::SINGLETON_ID,
        'is_active' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Ambil baris pengaturan, buat bila belum ada.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => self::SINGLETON_ID]);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
