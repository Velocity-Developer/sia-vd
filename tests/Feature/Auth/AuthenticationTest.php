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
    ])->assertSessionHasErrors(['username' => 'NIM/NIDN/username atau kata sandi tidak cocok.']);

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

test('mahasiswa dapat masuk memakai NIM', function () {
    $user = User::factory()->mahasiswa()->create();

    $this->post('/login', [
        'username' => $user->mahasiswaProfile->nim,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('dosen dapat masuk memakai NIDN', function () {
    $user = User::factory()->dosen()->create();

    $this->post('/login', [
        'username' => ' '.$user->dosenProfile->nidn.' ',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('username didahulukan bila sama dengan NIM akun lain', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();
    $pemilikUsername = User::factory()->create(['username' => $mahasiswa->mahasiswaProfile->nim]);

    $this->post('/login', [
        'username' => $mahasiswa->mahasiswaProfile->nim,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($pemilikUsername);
});

test('NIM tak dikenal ditolak dengan pesan biasa', function () {
    $this->post('/login', [
        'username' => '999999999',
        'password' => 'password',
    ])->assertSessionHasErrors(['username' => 'NIM/NIDN/username atau kata sandi tidak cocok.']);

    $this->assertGuest();
});

test('dosen nonaktif ditolak masuk dengan pesan', function () {
    $dosen = User::factory()->dosen()->create();
    $dosen->dosenProfile->update(['status' => 'Nonaktif']);

    $this->post('/login', ['username' => $dosen->username, 'password' => 'password'])
        ->assertSessionHasErrors(['username' => 'Akun dosen Anda berstatus nonaktif. Hubungi admin akademik untuk mengaktifkan kembali.']);

    $this->assertGuest();
});

test('mahasiswa berstatus selain aktif ditolak masuk dengan pesan', function (string $status) {
    $mahasiswa = User::factory()->create();
    $mahasiswa->mahasiswaProfile->update(['status' => $status]);

    $this->post('/login', ['username' => $mahasiswa->username, 'password' => 'password'])
        ->assertSessionHasErrors(['username' => "Akun tidak dapat digunakan karena status mahasiswa Anda: {$status}. Hubungi admin akademik."]);

    $this->assertGuest();
})->with(['Nonaktif', 'Dropout', 'Mengundurkan Diri']);

test('mahasiswa lulus dan cuti tetap bisa masuk', function (string $status) {
    $mahasiswa = User::factory()->create();
    $mahasiswa->mahasiswaProfile->update(['status' => $status]);

    $this->post('/login', ['username' => $mahasiswa->username, 'password' => 'password'])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($mahasiswa);
})->with(['Lulus', 'Cuti']);

test('status akun tidak dibocorkan bila kata sandi salah', function () {
    $dosen = User::factory()->dosen()->create();
    $dosen->dosenProfile->update(['status' => 'Nonaktif']);

    $this->post('/login', ['username' => $dosen->username, 'password' => 'salah'])
        ->assertSessionHasErrors(['username' => 'NIM/NIDN/username atau kata sandi tidak cocok.']);
});

test('sesi yang terbuka berakhir begitu akun dinonaktifkan', function () {
    $dosen = User::factory()->dosen()->create();
    $this->actingAs($dosen)->get(route('dosen.dashboard'))->assertOk();

    $dosen->dosenProfile->update(['status' => 'Nonaktif']);

    $this->withHeader('X-Inertia', 'true')->get(route('dosen.dashboard'))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));
    $this->assertGuest();
});
