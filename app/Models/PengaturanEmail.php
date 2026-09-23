<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Pengaturan pengiriman surel (SMTP) yang bisa diubah admin lewat halaman pengaturan,
 * supaya tidak perlu menyunting berkas .env di server.
 */
class PengaturanEmail extends Model
{
    use SerializesDatesInAppTimezone;

    public const SINGLETON_ID = 1;

    protected $table = 'pengaturan_email';

    protected $fillable = ['mailer', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name', 'updated_by'];

    protected $attributes = [
        'id' => self::SINGLETON_ID,
        'mailer' => 'log',
    ];

    /** Kata sandi SMTP tidak pernah ikut terkirim ke browser. */
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => self::SINGLETON_ID], ['mailer' => Config::get('mail.default', 'log')]);
    }

    /**
     * Pakai pengaturan dari basis data sebagai konfigurasi mail aplikasi.
     * Dipanggil saat mailer pertama kali dibuat, jadi tidak menambah kueri di halaman biasa.
     */
    public static function terapkan(): void
    {
        try {
            $pengaturan = static::query()->find(self::SINGLETON_ID);
        } catch (Throwable) {
            // Tabel belum ada (migrasi pertama) — biarkan konfigurasi dari .env yang dipakai.
            return;
        }

        if (! $pengaturan) {
            return;
        }

        Config::set('mail.default', $pengaturan->mailer);

        if ($pengaturan->from_address) {
            Config::set('mail.from.address', $pengaturan->from_address);
            Config::set('mail.from.name', $pengaturan->from_name ?: $pengaturan->from_address);
        }

        if ($pengaturan->mailer !== 'smtp') {
            return;
        }

        Config::set('mail.mailers.smtp.host', $pengaturan->host);
        Config::set('mail.mailers.smtp.port', $pengaturan->port);
        Config::set('mail.mailers.smtp.username', $pengaturan->username);
        Config::set('mail.mailers.smtp.password', $pengaturan->password);
        Config::set('mail.mailers.smtp.scheme', $pengaturan->encryption === 'ssl' ? 'smtps' : 'smtp');
        Config::set('mail.mailers.smtp.encryption', $pengaturan->encryption === 'none' ? null : $pengaturan->encryption);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
