<?php

namespace App;

use App\Models\PengaturanFitur;

/**
 * Status fitur per klien dari config/client.php (lihat penjelasan di sana) dan override di database.
 *
 * Urutan penentuan status satu fitur:
 * 1. tidak terdaftar di config = mati;
 * 2. `locked` = ikut `default` di config (override database diabaikan);
 * 3. ada override di tabel pengaturan_fitur = ikut override;
 * 4. selain itu ikut `default` di config.
 * Setelah itu semua fitur di `butuh` juga harus aktif (ditentukan dengan urutan yang sama).
 * Dependensi yang melingkar dianggap mati agar pengecekan tidak berputar tanpa akhir.
 */
class Feature
{
    public static function aktif(string $nama): bool
    {
        return static::aktifDengan($nama, PengaturanFitur::semua());
    }

    /**
     * Status fitur bila override database-nya berisi $override (dipakai untuk menguji perubahan sebelum disimpan).
     *
     * @param  array<string, bool>  $override
     */
    public static function aktifDengan(string $nama, array $override): bool
    {
        return static::cek($nama, $override, []);
    }

    /**
     * Status fitur itu sendiri (langkah 1–4), tanpa memperhitungkan dependensinya.
     *
     * @param  array<string, bool>  $override
     */
    public static function nyalaSendiri(string $nama, array $override): bool
    {
        $fitur = config("client.fitur.{$nama}");

        if (! is_array($fitur)) {
            return false;
        }

        $bawaan = filter_var($fitur['default'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (static::terkunci($nama)) {
            return $bawaan;
        }

        return $override[$nama] ?? $bawaan;
    }

    public static function terkunci(string $nama): bool
    {
        return filter_var(config("client.fitur.{$nama}.locked", false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, bool>  $override
     * @param  list<string>  $jejak  fitur yang sedang dicek di atas rantai dependensi ini
     */
    private static function cek(string $nama, array $override, array $jejak): bool
    {
        if (in_array($nama, $jejak, true) || ! static::nyalaSendiri($nama, $override)) {
            return false;
        }

        foreach ((array) config("client.fitur.{$nama}.butuh", []) as $butuh) {
            if (! static::cek((string) $butuh, $override, [...$jejak, $nama])) {
                return false;
            }
        }

        return true;
    }
}
