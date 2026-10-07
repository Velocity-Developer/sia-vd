<?php

use App\CaptchaGambar;
use App\Models\User;

test('halaman masuk menampilkan alamat gambar captcha', function () {
    $this->get('/login')->assertInertia(fn ($page) => $page->component('auth/Login')->where('captchaUrl', '/login/captcha'));
});

test('gambar captcha berupa PNG tanpa cache dan menanam kode di sesi', function () {
    $response = $this->get('/login/captcha')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(substr($response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(session(CaptchaGambar::KUNCI_SESI)['kode'])->toMatch('/^[A-Z0-9]{'.CaptchaGambar::PANJANG.'}$/');
});

test('masuk ditolak tanpa captcha atau dengan kode salah', function () {
    $user = User::factory()->create();

    $this->post('/login', ['username' => $user->username, 'password' => 'password'])
        ->assertSessionHasErrors(['captcha' => 'Ketik kode pada gambar captcha.']);

    $this->withSession([CaptchaGambar::KUNCI_SESI => ['kode' => 'ACDEF', 'kedaluwarsa' => now()->addMinute()->getTimestamp()]])
        ->post('/login', ['username' => $user->username, 'password' => 'password', 'captcha' => 'XXXXX'])
        ->assertSessionHasErrors('captcha');

    $this->assertGuest();
});

test('kode captcha tidak membedakan huruf besar kecil dan hanya sekali pakai', function () {
    $user = User::factory()->create();
    $kode = ['kode' => 'ACDEF', 'kedaluwarsa' => now()->addMinute()->getTimestamp()];

    // Kata sandi salah tetap menghabiskan kode, jadi kode yang sama tidak bisa dipakai menebak ulang.
    $this->withSession([CaptchaGambar::KUNCI_SESI => $kode])
        ->post('/login', ['username' => $user->username, 'password' => 'salah', 'captcha' => 'acdef'])
        ->assertSessionHasErrors('username');
    $this->post('/login', ['username' => $user->username, 'password' => 'password', 'captcha' => 'ACDEF'])
        ->assertSessionHasErrors('captcha');
    $this->assertGuest();

    $this->withSession([CaptchaGambar::KUNCI_SESI => $kode])
        ->post('/login', ['username' => $user->username, 'password' => 'password', 'captcha' => ' acdef '])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
});

test('kode captcha kedaluwarsa ditolak', function () {
    $user = User::factory()->create();

    $this->withSession([CaptchaGambar::KUNCI_SESI => ['kode' => 'ACDEF', 'kedaluwarsa' => now()->subSecond()->getTimestamp()]])
        ->post('/login', ['username' => $user->username, 'password' => 'password', 'captcha' => 'ACDEF'])
        ->assertSessionHasErrors('captcha');
});
