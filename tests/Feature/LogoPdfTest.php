<?php

use App\Models\PengaturanInstitusi;
use Illuminate\Support\Facades\Storage;

it('embeds a downscaled logo in PDFs', function () {
    Storage::fake('public');
    $gambar = imagecreatetruecolor(600, 400);
    ob_start();
    imagewebp($gambar);
    Storage::disk('public')->put('institusi/logo.webp', ob_get_clean());
    PengaturanInstitusi::current()->update(['logo' => 'institusi/logo.webp']);

    $uri = PengaturanInstitusi::current()->logoDataUri();
    [$lebar, $tinggi] = getimagesizefromstring(base64_decode(substr($uri, strlen('data:image/png;base64,'))));

    expect($uri)->toStartWith('data:image/png;base64,')
        ->and([$lebar, $tinggi])->toBe([180, 120]);
});

it('keeps a small logo as is', function () {
    Storage::fake('public');
    $gambar = imagecreatetruecolor(100, 100);
    ob_start();
    imagepng($gambar);
    Storage::disk('public')->put('institusi/kecil.png', $isi = ob_get_clean());
    PengaturanInstitusi::current()->update(['logo' => 'institusi/kecil.png']);

    expect(PengaturanInstitusi::current()->logoDataUri())->toBe('data:image/png;base64,'.base64_encode($isi));
});
