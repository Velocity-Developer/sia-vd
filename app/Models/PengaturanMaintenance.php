<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\UserType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Mode maintenance untuk dosen dan/atau mahasiswa. Admin/karyawan, developer, dan pemegang izin
 * admin.pengaturan-maintenance tetap bisa masuk dan bekerja seperti biasa.
 */
class PengaturanMaintenance extends Model
{
    use SerializesDatesInAppTimezone;

    public const SINGLETON_ID = 1;

    // Kolom id bukan auto-increment: tanpa ini, simpanan pertama di MySQL memakai lastInsertId (0) dan
    // update berikutnya pada model yang sama tidak mengenai baris mana pun.
    public $incrementing = false;

    public const SHARED_CACHE_KEY = 'pengaturan-maintenance';

    public const PESAN_BAWAAN = 'Sistem sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.';

    public const IZIN = 'admin.pengaturan-maintenance';

    protected $table = 'pengaturan_maintenance';

    protected $fillable = ['aktif', 'untuk_dosen', 'untuk_mahasiswa', 'pesan', 'perkiraan_selesai', 'updated_by'];

    protected $attributes = [
        'id' => self::SINGLETON_ID,
        'aktif' => false,
        'untuk_dosen' => true,
        'untuk_mahasiswa' => true,
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'untuk_dosen' => 'boolean',
            'untuk_mahasiswa' => 'boolean',
            'perkiraan_selesai' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => self::SINGLETON_ID]);
    }

    /**
     * Status maintenance yang dibaca setiap permintaan, jadi disimpan di cache dan dibuang saat disimpan.
     *
     * @return array{aktif: bool, untuk: list<string>, pesan: string, perkiraan_selesai: ?string}
     */
    public static function shared(): array
    {
        $data = Cache::rememberForever(self::SHARED_CACHE_KEY, function (): array {
            try {
                $pengaturan = static::query()->find(self::SINGLETON_ID);
            } catch (Throwable) {
                // Tabel belum ada (migrasi belum jalan).
                $pengaturan = null;
            }

            $untuk = array_values(array_filter([
                $pengaturan?->untuk_dosen ? UserType::Dosen->value : null,
                $pengaturan?->untuk_mahasiswa ? UserType::Mahasiswa->value : null,
            ]));

            return [
                'aktif' => (bool) $pengaturan?->aktif && $untuk !== [],
                'untuk' => $untuk,
                'pesan' => $pengaturan?->pesan ?: self::PESAN_BAWAAN,
                'perkiraan_selesai' => $pengaturan?->perkiraan_selesai?->format('Y-m-d H:i:s'),
            ];
        });

        // Label jam dibentuk di luar cache supaya ikut zona waktu institusi yang berlaku sekarang.
        $data['perkiraan_selesai'] = $data['perkiraan_selesai']
            ? Carbon::parse($data['perkiraan_selesai'])->translatedFormat('j F Y, H.i').' '.PengaturanInstitusi::singkatanZona()
            : null;

        return $data;
    }

    public static function lupakanCache(): void
    {
        Cache::forget(self::SHARED_CACHE_KEY);
    }

    /**
     * Apakah pengguna ini sedang tertahan mode maintenance.
     */
    public static function menghalangi(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $status = self::shared();
        if (! $status['aktif']) {
            return false;
        }

        if ($user->isDeveloper() || $user->hasPermission(self::IZIN)) {
            return false;
        }

        // Jenis Admin/Karyawan tidak pernah ada di daftar "untuk".
        return in_array($user->type()?->value, $status['untuk'], true);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::lupakanCache());
        static::deleted(fn () => self::lupakanCache());
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
