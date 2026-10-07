<?php

use App\Models\InformasiPmb;

it('lists only public pages in sitemap.xml', function () {
    InformasiPmb::query()->updateOrCreate(['id' => InformasiPmb::SINGLETON_ID], ['syarat' => 'Ijazah']);

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = simplexml_load_string($response->getContent());
    $lokasi = array_map(fn ($url): string => (string) $url->loc, iterator_to_array($xml->url, false));
    $akar = rtrim(config('app.url'), '/');
    expect($lokasi)->toBe([$akar.'/pmb', $akar.'/pmb/daftar', $akar.'/login', $akar.'/pengumuman', $akar.'/kalender-akademik'])
        ->and((string) $xml->url[0]->lastmod)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

it('serves robots.txt that hides private areas and points to the sitemap', function () {
    $isi = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();

    expect($isi)->toContain('Disallow: /admin')
        ->toContain('Disallow: /mahasiswa')
        ->toContain('Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml')
        ->not->toContain('Disallow: /pmb'."\n");
});
