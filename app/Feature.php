<?php

namespace App;

/**
 * Status fitur per klien dari config/client.php (lihat penjelasan di sana).
 *
 * Fitur aktif bila terdaftar, `default`-nya true, dan semua fitur di `butuh` juga aktif.
 * Dependensi yang melingkar dianggap mati agar pengecekan tidak berputar tanpa akhir.
 */
class Feature
{
    public static function aktif(string $nama): bool
    {
        return static::cek($nama, []);
    }

    /**
     * @param  list<string>  $jejak  fitur yang sedang dicek di atas rantai dependensi ini
     */
    private static function cek(string $nama, array $jejak): bool
    {
        $fitur = config("client.fitur.{$nama}");

        if (! is_array($fitur) || in_array($nama, $jejak, true) || ! filter_var($fitur['default'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        foreach ((array) ($fitur['butuh'] ?? []) as $butuh) {
            if (! static::cek((string) $butuh, [...$jejak, $nama])) {
                return false;
            }
        }

        return true;
    }
}
