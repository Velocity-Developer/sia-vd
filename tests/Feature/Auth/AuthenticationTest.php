<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('mahasiswa.dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    // Pesannya harus berbahasa Indonesia, bukan kunci mentah "auth.failed".
    $this->post('/login', [
        'username' => $user->username,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['username' => 'Username atau kata sandi tidak cocok.']);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('permintaan inertia saat masuk memicu muat ulang penuh', function () {
    $user = User::factory()->create();

    // Daftar route Ziggy ditanam di Blade sesuai izin pengguna, jadi masuk lewat Inertia
    // harus dijawab dengan muat ulang penuh; tanpa itu menu peran gagal dibentuk di browser.
    $response = $this->withHeader('X-Inertia', 'true')->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', route('mahasiswa.dashboard'));
});

test('permintaan inertia saat keluar memicu muat ulang penuh', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->withHeader('X-Inertia', 'true')->post('/logout');

    $this->assertGuest();
    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', url('/'));
});
