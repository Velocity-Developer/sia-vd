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
        // Nama aplikasi dan favicon bawaan diturunkan dari data institusi, jadi cache tampilan ikut dihapus.
        static::saved(function (): void {
            Cache::forget(self::SHARED_CACHE_KEY);
            PengaturanTampilan::lupakanCache();
        });
        static::deleted(function (): void {
            Cache::forget(self::SHARED_CACHE_KEY);
            PengaturanTampilan::lupakanCache();
        });
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

        // Logo di kop PDF hanya selebar ±58 px, tetapi dompdf menyimpan gambar dalam ukuran aslinya (logo
        // 600 px = ±120 KB per PDF). Versi kecil dibuat sekali per berkas logo; nama berkas logo berganti
        // setiap diunggah ulang, jadi cache tidak perlu dibuang manual.
        return Cache::rememberForever('logo-pdf:'.md5($this->logo), function () use ($disk): string {
            $isi = $disk->get($this->logo);

            return self::logoKecil($isi) ?? 'data:'.($disk->mimeType($this->logo) ?: 'image/png').';base64,'.base64_encode($isi);
        });
    }

    /**
     * Perkecil logo menjadi PNG (transparansi dipertahankan) dengan sisi terpanjang maksimal $sisi px.
     * Null bila gambar tidak bisa dibaca GD, atau sudah cukup kecil.
     */
    private static function logoKecil(string $isi, int $sisi = 180): ?string
    {
        $gambar = function_exists('imagecreatefromstring') ? @imagecreatefromstring($isi) : false;

        if ($gambar === false) {
            return null;
        }

        [$lebar, $tinggi] = [imagesx($gambar), imagesy($gambar)];

        if (max($lebar, $tinggi) <= $sisi) {
            return null;
        }

        $skala = $sisi / max($lebar, $tinggi);
        [$w, $h] = [max(1, (int) round($lebar * $skala)), max(1, (int) round($tinggi * $skala))];
        $kecil = imagecreatetruecolor($w, $h);
        imagealphablending($kecil, false);
        imagesavealpha($kecil, true);
        imagefill($kecil, 0, 0, imagecolorallocatealpha($kecil, 0, 0, 0, 127));
        imagecopyresampled($kecil, $gambar, 0, 0, 0, 0, $w, $h, $lebar, $tinggi);

        ob_start();
        imagepng($kecil, null, 9);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
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
