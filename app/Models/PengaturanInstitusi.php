<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PengaturanInstitusi extends Model
{
    use SerializesDatesInAppTimezone;

    /**
     * Baris singleton yang selalu dipakai.
     */
    public const SINGLETON_ID = 1;

    private const SHARED_CACHE_KEY = 'institusi.shared';

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
        // Dibaca di setiap halaman, jadi disimpan di cache; dihapus otomatis saat pengaturan disimpan (lihat booted()).
        return Cache::rememberForever(self::SHARED_CACHE_KEY, function (): array {
            $institusi = static::query()->find(self::SINGLETON_ID);

            return [
                'nama_pt' => $institusi?->nama_pt ?? config('app.name'),
                'singkatan' => $institusi?->singkatan,
                'logo_url' => $institusi?->logo_url,
            ];
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::SHARED_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::SHARED_CACHE_KEY));
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo ? '/storage/'.ltrim($this->logo, '/') : null);
    }

    /**
     * Logo sebagai data URI untuk kop dokumen PDF (dompdf tidak mengambil gambar lewat URL).
     */
    public function logoDataUri(): ?string
    {
        $disk = Storage::disk('public');

        if ($this->logo === null || ! $disk->exists($this->logo)) {
            return null;
        }

        $mime = $disk->mimeType($this->logo) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode($disk->get($this->logo));
    }

    /**
     * Baris kontak di kop dokumen: alamat, telepon, surel, situs.
     *
     * @return list<string>
     */
    public function kontakKop(): array
    {
        return array_values(array_filter([
            $this->alamat,
            $this->telepon ? "Telp. {$this->telepon}" : null,
            $this->email,
            $this->website,
        ]));
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
