<?php

namespace App\Http\Controllers;

use App\Models\InformasiPmb;
use Illuminate\Http\Response;

/**
 * sitemap.xml dan robots.txt untuk mesin pencari. Hanya halaman publik (tanpa login) yang dimasukkan;
 * alamatnya mengikuti APP_URL, jadi isi di dev dan produksi menyesuaikan sendiri.
 */
class SeoController extends Controller
{
    /** Awalan halaman yang perlu login atau bukan untuk diindeks. */
    private const TERLARANG = ['/admin', '/dosen', '/mahasiswa', '/pengaturan-sistem', '/dev', '/berkas', '/settings', '/pmb/selesai', '/pmb/kecamatan'];

    public function sitemap(): Response
    {
        $diubahPmb = InformasiPmb::query()->max('updated_at');

        $halaman = [
            ['loc' => $this->alamat('pmb.informasi'), 'lastmod' => $diubahPmb, 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => $this->alamat('pmb.daftar'), 'lastmod' => null, 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $this->alamat('login'), 'lastmod' => null, 'changefreq' => 'yearly', 'priority' => '0.5'],
        ];

        return response()
            ->view('seo.sitemap', ['halaman' => array_map(fn (array $h): array => [
                ...$h,
                'lastmod' => $h['lastmod'] ? substr((string) $h['lastmod'], 0, 10) : null,
            ], $halaman)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Alamat lengkap memakai APP_URL (bukan host permintaan), agar www/http atau IP tidak ikut terdaftar.
     */
    private function alamat(string $rute): string
    {
        return rtrim((string) config('app.url'), '/').route($rute, [], false);
    }

    public function robots(): Response
    {
        $baris = ['User-agent: *', ...array_map(fn (string $awalan): string => 'Disallow: '.$awalan, self::TERLARANG), '', 'Sitemap: '.$this->alamat('sitemap')];

        return response(implode("\n", $baris)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
