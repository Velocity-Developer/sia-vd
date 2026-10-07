<?php

namespace App;

use GdImage;
use Illuminate\Contracts\Session\Session;

/**
 * Captcha gambar di halaman masuk: kode acak disimpan di sesi lalu digambar sebagai PNG (ekstensi GD).
 * Kode hanya sekali pakai — setiap percobaan masuk menghapusnya, cocok atau tidak.
 */
class CaptchaGambar
{
    public const KUNCI_SESI = 'captcha_login';

    public const PANJANG = 5;

    public const BERLAKU_MENIT = 10;

    // Tanpa huruf/angka yang mirip satu sama lain (0/O, 1/I/L, 2/Z, 5/S, 8/B).
    private const KARAKTER = 'ACDEFGHJKMNPQRTUVWXY34679';

    private const LEBAR = 180;

    private const TINGGI = 56;

    public function __construct(private Session $sesi) {}

    /**
     * Buat kode baru (menggantikan kode lama) dan kembalikan gambarnya dalam format PNG.
     */
    public function baru(): string
    {
        $kode = '';
        for ($i = 0; $i < self::PANJANG; $i++) {
            $kode .= self::KARAKTER[random_int(0, strlen(self::KARAKTER) - 1)];
        }

        $this->sesi->put(self::KUNCI_SESI, ['kode' => $kode, 'kedaluwarsa' => now()->addMinutes(self::BERLAKU_MENIT)->getTimestamp()]);

        return $this->gambar($kode);
    }

    /**
     * Cocokkan isian pengguna (tanpa membedakan huruf besar/kecil), lalu buang kodenya.
     */
    public function cocok(?string $isian): bool
    {
        $simpanan = $this->sesi->pull(self::KUNCI_SESI);

        if (! is_array($simpanan) || blank($isian) || ($simpanan['kedaluwarsa'] ?? 0) < now()->getTimestamp()) {
            return false;
        }

        return hash_equals((string) $simpanan['kode'], strtoupper(trim($isian)));
    }

    private function gambar(string $kode): string
    {
        $gambar = imagecreatetruecolor(self::LEBAR, self::TINGGI);
        imagefill($gambar, 0, 0, imagecolorallocate($gambar, 246, 245, 244));

        // Bintik dan garis acak di belakang huruf agar kode tidak mudah dibaca mesin.
        for ($i = 0; $i < 350; $i++) {
            imagesetpixel($gambar, random_int(0, self::LEBAR - 1), random_int(0, self::TINGGI - 1), $this->warnaAcak($gambar, 140, 210));
        }
        for ($i = 0; $i < 5; $i++) {
            imagesetthickness($gambar, random_int(1, 2));
            imageline($gambar, 0, random_int(0, self::TINGGI), self::LEBAR, random_int(0, self::TINGGI), $this->warnaAcak($gambar, 120, 190));
        }

        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        $jarak = (self::LEBAR - 20) / self::PANJANG;

        foreach (str_split($kode) as $i => $huruf) {
            $warna = $this->warnaAcak($gambar, 20, 100);
            $x = (int) (12 + $i * $jarak + random_int(-3, 3));

            if (is_file($font) && function_exists('imagettftext')) {
                imagettftext($gambar, random_int(22, 27), random_int(-25, 25), $x, random_int(36, 46), $warna, $font, $huruf);
            } else {
                imagestring($gambar, 5, $x + 6, random_int(14, 26), $huruf, $warna);
            }
        }

        // Dua garis di depan huruf, tipis agar kode tetap terbaca manusia.
        imagesetthickness($gambar, 1);
        for ($i = 0; $i < 2; $i++) {
            imageline($gambar, 0, random_int(10, self::TINGGI - 10), self::LEBAR, random_int(10, self::TINGGI - 10), $this->warnaAcak($gambar, 60, 130));
        }

        ob_start();
        imagepng($gambar);

        return (string) ob_get_clean();
    }

    private function warnaAcak(GdImage $gambar, int $min, int $maks): int
    {
        return imagecolorallocate($gambar, random_int($min, $maks), random_int($min, $maks), random_int($min, $maks));
    }
}
