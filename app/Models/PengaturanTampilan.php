<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan tampilan sistem (satu baris). Kolom kosong berarti memakai bawaan aplikasi.
 */
class PengaturanTampilan extends Model
{
    use SerializesDatesInAppTimezone;

    public const SINGLETON_ID = 1;

    public const SIDEBAR = ['lebar', 'ringkas'];

    public const LOGIN_JUDUL_BAWAAN = 'Sistem Informasi Akademik';

    public const LOGIN_TEKS_BAWAAN = 'Satu akun untuk rencana studi, perkuliahan, nilai, dan administrasi Anda.';

    private const SHARED_CACHE_KEY = 'tampilan.shared';

    protected $table = 'pengaturan_tampilan';

    protected $fillable = ['nama_aplikasi', 'favicon', 'login_judul', 'login_teks', 'login_gambar', 'login_sorotan', 'sidebar_bawaan', 'updated_by'];

    protected $attributes = [
        'id' => self::SINGLETON_ID,
        'login_sorotan' => true,
        'sidebar_bawaan' => 'lebar',
    ];

    protected function casts(): array
    {
        return [
            'login_sorotan' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => self::SINGLETON_ID]);
    }

    /**
     * Nilai yang dipakai di seluruh halaman (sudah terisi bawaan), disimpan di cache dan dihapus saat
     * pengaturan tampilan atau institusi disimpan.
     *
     * @return array{nama_aplikasi: string, favicon_url: string, login_judul: string, login_teks: string, login_gambar_url: ?string, login_sorotan: bool, sidebar_bawaan: string}
     */
    public static function shared(): array
    {
        return Cache::rememberForever(self::SHARED_CACHE_KEY, function (): array {
            $tampilan = static::query()->find(self::SINGLETON_ID) ?? new static;
            $institusi = PengaturanInstitusi::shared();

            return [
                'nama_aplikasi' => $tampilan->nama_aplikasi ?: ($institusi['singkatan'] ?: $institusi['nama_pt']),
                // Tanpa favicon khusus, dipakai logo institusi, lalu ikon bawaan aplikasi.
                'favicon_url' => self::urlBerkas($tampilan->favicon) ?? $institusi['logo_url'] ?? '/favicon.ico',
                'login_judul' => $tampilan->login_judul ?: self::LOGIN_JUDUL_BAWAAN,
                'login_teks' => $tampilan->login_teks ?: self::LOGIN_TEKS_BAWAAN,
                'login_gambar_url' => self::urlBerkas($tampilan->login_gambar),
                'login_sorotan' => $tampilan->login_sorotan,
                'sidebar_bawaan' => $tampilan->sidebar_bawaan,
            ];
        });
    }

    public static function lupakanCache(): void
    {
        Cache::forget(self::SHARED_CACHE_KEY);
    }

    public static function urlBerkas(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::lupakanCache());
        static::deleted(fn () => self::lupakanCache());
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
