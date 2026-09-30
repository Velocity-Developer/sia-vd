<?php

namespace App\Models;

use App\Feature;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Override status fitur per klien (satu baris per fitur). Fitur tanpa baris di sini ikut bawaan config/client.php;
 * fitur yang `locked` selalu ikut config dan tidak bisa di-override. Urutan lengkapnya ada di App\Feature.
 */
class PengaturanFitur extends Model
{
    use SerializesDatesInAppTimezone;

    private const CACHE_KEY = 'fitur.override';

    protected $table = 'pengaturan_fitur';

    protected $fillable = ['nama', 'aktif', 'diubah_oleh'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    /**
     * Semua override [nama => aktif], dibaca dengan satu query lalu disimpan di cache (dan di memori selama request).
     * Bila tabelnya belum ada (misalnya sebelum migrasi), dianggap tanpa override dan hasilnya tidak di-cache.
     *
     * @return array<string, bool>
     */
    public static function semua(): array
    {
        $cache = Cache::memo();
        $override = $cache->get(self::CACHE_KEY);

        if (is_array($override)) {
            return $override;
        }

        try {
            $override = static::query()->pluck('aktif', 'nama')->map(fn ($aktif): bool => (bool) $aktif)->all();
        } catch (QueryException) {
            return [];
        }

        $cache->forever(self::CACHE_KEY, $override);

        return $override;
    }

    /**
     * Menyalakan/mematikan fitur lewat database, atau kembali ke bawaan config bila $aktif null.
     *
     * Override dan log-nya disimpan dalam satu transaksi, lalu cache dibuang. Perubahan ditolak bila membuat
     * status fitur lain ikut berubah diam-diam: fitur yang dinyalakan tetapi dependensinya mati, atau fitur
     * aktif lain yang membutuhkan fitur yang dimatikan. Nilai yang sama tidak dicatat ulang.
     *
     * @throws ValidationException bila dependensi tidak terpenuhi (kunci `aktif`)
     */
    public static function atur(string $nama, ?bool $aktif, ?User $oleh = null): void
    {
        if (! is_array(config("client.fitur.{$nama}"))) {
            throw new InvalidArgumentException("Fitur {$nama} tidak terdaftar di config/client.php.");
        }

        if (Feature::terkunci($nama)) {
            throw new InvalidArgumentException("Fitur {$nama} dikunci developer dan tidak bisa diubah.");
        }

        DB::transaction(function () use ($nama, $aktif, $oleh): void {
            // Semua baris dikunci agar dua perubahan bersamaan tidak lolos cek dependensi masing-masing.
            $lamaSemua = static::query()->lockForUpdate()->pluck('aktif', 'nama')->map(fn ($v): bool => (bool) $v)->all();
            $lama = $lamaSemua[$nama] ?? null;

            if ($lama === $aktif) {
                return;
            }

            $baruSemua = $lamaSemua;
            if ($aktif === null) {
                unset($baruSemua[$nama]);
            } else {
                $baruSemua[$nama] = $aktif;
            }

            static::pastikanDependensi($nama, $lamaSemua, $baruSemua);

            if ($aktif === null) {
                static::query()->where('nama', $nama)->delete();
            } else {
                static::query()->updateOrCreate(['nama' => $nama], ['aktif' => $aktif, 'diubah_oleh' => $oleh?->id]);
            }

            LogPengaturanFitur::create(['nama' => $nama, 'aktif_lama' => $lama, 'aktif_baru' => $aktif, 'diubah_oleh' => $oleh?->id]);
        });

        self::lupakanCache();
    }

    /**
     * @param  array<string, bool>  $lama  override sebelum perubahan
     * @param  array<string, bool>  $baru  override sesudah perubahan
     */
    private static function pastikanDependensi(string $nama, array $lama, array $baru): void
    {
        $label = fn (string $n): string => (string) config("client.fitur.{$n}.label", $n);

        if (Feature::nyalaSendiri($nama, $baru) && ! Feature::aktifDengan($nama, $baru)) {
            $mati = collect((array) config("client.fitur.{$nama}.butuh", []))
                ->reject(fn (string $b): bool => Feature::aktifDengan($b, $baru))
                ->map($label)->implode(', ');

            throw ValidationException::withMessages(['aktif' => "{$label($nama)} membutuhkan fitur yang masih mati: {$mati}. Nyalakan fitur itu dulu."]);
        }

        $ikutMati = collect(array_keys((array) config('client.fitur')))
            ->reject(fn (string $n): bool => $n === $nama)
            ->filter(fn (string $n): bool => Feature::aktifDengan($n, $lama) && ! Feature::aktifDengan($n, $baru))
            ->map($label);

        if ($ikutMati->isNotEmpty()) {
            throw ValidationException::withMessages(['aktif' => "Fitur {$ikutMati->implode(', ')} membutuhkan {$label($nama)}. Matikan fitur itu dulu."]);
        }
    }

    public static function lupakanCache(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::lupakanCache());
        static::deleted(fn () => self::lupakanCache());
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
