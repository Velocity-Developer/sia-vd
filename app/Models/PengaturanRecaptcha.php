<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengaturan Google reCAPTCHA v2 (kotak centang "Saya bukan robot") di halaman masuk.
 */
class PengaturanRecaptcha extends Model
{
    use SerializesDatesInAppTimezone;

    public const SINGLETON_ID = 1;

    // Kolom id bukan auto-increment: tanpa ini, simpanan pertama di MySQL memakai lastInsertId (0) dan
    // update berikutnya pada model yang sama tidak mengenai baris mana pun.
    public $incrementing = false;

    public const URL_VERIFIKASI = 'https://www.google.com/recaptcha/api/siteverify';

    protected $table = 'pengaturan_recaptcha';

    protected $fillable = ['aktif', 'site_key', 'secret_key', 'updated_by'];

    protected $attributes = [
        'id' => self::SINGLETON_ID,
        'aktif' => false,
    ];

    /** Secret key tidak pernah ikut terkirim ke browser. */
    protected $hidden = ['secret_key'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'secret_key' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => self::SINGLETON_ID]);
    }

    /**
     * Site key untuk halaman masuk, atau null bila captcha mati / kuncinya belum lengkap.
     */
    public static function siteKeyLogin(): ?string
    {
        try {
            $pengaturan = static::query()->find(self::SINGLETON_ID);
        } catch (Throwable) {
            // Tabel belum ada (migrasi belum jalan) — halaman masuk tetap bisa dipakai.
            return null;
        }

        return $pengaturan?->dipakai() ? $pengaturan->site_key : null;
    }

    public function dipakai(): bool
    {
        return $this->aktif && filled($this->site_key) && filled($this->secret_key);
    }

    /**
     * Cocokkan token dari widget ke Google. Gagal terhubung dianggap tidak lolos.
     */
    public static function verifikasi(?string $secretKey, ?string $token, ?string $ip = null): bool
    {
        if (blank($secretKey) || blank($token)) {
            return false;
        }

        try {
            $hasil = Http::asForm()->timeout(10)->post(self::URL_VERIFIKASI, array_filter([
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (Throwable $e) {
            Log::warning('Verifikasi reCAPTCHA gagal terhubung: '.$e->getMessage());

            return false;
        }

        if (! $hasil->json('success')) {
            Log::info('reCAPTCHA ditolak Google.', ['error-codes' => $hasil->json('error-codes')]);

            return false;
        }

        return true;
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
