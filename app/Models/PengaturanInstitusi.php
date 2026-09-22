<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanInstitusi extends Model
{
    use SerializesDatesInAppTimezone;

    /**
     * Baris singleton yang selalu dipakai.
     */
    public const SINGLETON_ID = 1;

    protected $table = 'pengaturan_institusi';

    protected $fillable = ['nama_pt', 'singkatan', 'logo', 'npsn', 'alamat', 'telepon', 'email', 'website', 'tahun_berdiri', 'updated_by'];

    protected $attributes = [
        'id' => self::SINGLETON_ID,
    ];

    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return [
            'tahun_berdiri' => 'integer',
        ];
    }

    /**
     * Ambil baris pengaturan, buat bila belum ada.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => self::SINGLETON_ID], ['nama_pt' => 'SIA VD']);
    }

    /**
     * Data institusi untuk dibagikan ke seluruh halaman, tanpa menulis baris baru.
     *
     * @return array<string, string|null>
     */
    public static function shared(): array
    {
        $institusi = static::query()->find(self::SINGLETON_ID);

        return [
            'nama_pt' => $institusi?->nama_pt ?? config('app.name'),
            'singkatan' => $institusi?->singkatan,
            'logo_url' => $institusi?->logo_url,
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo ? '/storage/'.ltrim($this->logo, '/') : null);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
