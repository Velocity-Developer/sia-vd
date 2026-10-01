<?php

use App\Models\PengaturanMaintenance;
use App\Models\Role;
use App\Models\User;
use App\UserType;

function nyalakanMaintenance(array $ubah = []): void
{
    PengaturanMaintenance::current()->update([
        'aktif' => true, 'untuk_dosen' => true, 'untuk_mahasiswa' => true, 'pesan' => 'Perbaikan server.', ...$ubah,
    ]);
}

it('hanya bisa diatur pemegang izin', function () {
    $this->actingAs(User::factory()->dosen()->create())->get('/pengaturan-sistem/maintenance')->assertForbidden();

    $staf = Role::factory()->type(UserType::Admin)->withPermissions(['admin.dashboard', 'admin.institusi'])->create();
    $this->actingAs(User::factory()->withRole($staf)->create())
        ->put('/pengaturan-sistem/maintenance', ['aktif' => 1, 'untuk_dosen' => 1, 'untuk_mahasiswa' => 1])
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get('/pengaturan-sistem/maintenance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('PengaturanSistem/Maintenance')->where('pengaturan.aktif', false));
});

it('menyimpan pengaturan dan langsung berlaku', function () {
    $admin = User::factory()->admin()->create();
    $mahasiswa = User::factory()->mahasiswa()->create();

    $this->actingAs($mahasiswa)->get('/settings/profile')->assertOk();

    $this->actingAs($admin)->put('/pengaturan-sistem/maintenance', [
        'aktif' => 1, 'untuk_dosen' => 0, 'untuk_mahasiswa' => 1, 'pesan' => ' Migrasi data. ', 'perkiraan_selesai' => '2026-10-01T14:00',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(PengaturanMaintenance::current()->pesan)->toBe('Migrasi data.')
        ->and(PengaturanMaintenance::shared())->toMatchArray([
            'aktif' => true,
            'untuk' => ['mahasiswa'],
            'pesan' => 'Migrasi data.',
            'perkiraan_selesai' => '1 Oktober 2026, 14.00 WIB',
        ]);

    $this->actingAs($mahasiswa)->get('/settings/profile')->assertStatus(503);
});

it('meminta minimal satu jenis pengguna saat diaktifkan', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/pengaturan-sistem/maintenance', ['aktif' => 1, 'untuk_dosen' => 0, 'untuk_mahasiswa' => 0])
        ->assertSessionHasErrors('untuk');

    expect(PengaturanMaintenance::current()->aktif)->toBeFalse();
});

it('menahan dosen dan mahasiswa yang sesinya masih terbuka', function () {
    nyalakanMaintenance();

    $this->actingAs(User::factory()->mahasiswa()->create())->get('/settings/profile')
        ->assertStatus(503)
        ->assertHeader('Retry-After')
        ->assertInertia(fn ($page) => $page->component('Maintenance')->where('maintenance.pesan', 'Perbaikan server.'));

    $this->actingAs(User::factory()->dosen()->create())->get('/settings/profile')->assertStatus(503);
});

it('mengalihkan kunjungan inertia dan menjawab permintaan json dengan 503', function () {
    nyalakanMaintenance();
    $mahasiswa = User::factory()->mahasiswa()->create();

    $this->actingAs($mahasiswa)->get('/settings/profile', ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location');

    $this->actingAs($mahasiswa)->getJson('/settings/profile')
        ->assertStatus(503)
        ->assertJson(['maintenance' => true, 'message' => 'Perbaikan server.']);
});

it('hanya menahan jenis pengguna yang dipilih', function () {
    nyalakanMaintenance(['untuk_dosen' => false]);

    $this->actingAs(User::factory()->dosen()->create())->get('/settings/profile')->assertOk();
    $this->actingAs(User::factory()->mahasiswa()->create())->get('/settings/profile')->assertStatus(503);
});

it('tidak pernah menahan admin dan pemegang izin maintenance', function () {
    nyalakanMaintenance();

    $this->actingAs(User::factory()->admin()->create())->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('maintenance.aktif', true));

    // Dosen yang diberi izin pengaturan maintenance (mis. kaprodi pengelola sistem) tetap lolos.
    $kaprodi = Role::factory()->type(UserType::Dosen)->withPermissions(['dosen.dashboard', 'admin.pengaturan-maintenance'])->create();
    $this->actingAs(User::factory()->withRole($kaprodi)->create())->get('/settings/profile')->assertOk();
});

it('tetap mengizinkan keluar', function () {
    nyalakanMaintenance();

    $this->actingAs(User::factory()->mahasiswa()->create())->post('/logout');

    $this->assertGuest();
});

it('menolak dosen dan mahasiswa masuk, tetapi admin tetap bisa', function () {
    nyalakanMaintenance();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $admin = User::factory()->admin()->create();

    $this->post('/login', ['username' => $mahasiswa->username, 'password' => 'password'])
        ->assertSessionHasErrors(['username' => 'Perbaikan server.']);
    $this->assertGuest();

    // Kata sandi salah tetap mendapat pesan biasa, jadi status maintenance tidak menggantikan pesan kredensial.
    $this->post('/login', ['username' => $mahasiswa->username, 'password' => 'salah'])
        ->assertSessionHasErrors(['username' => 'NIM/NIDN/username atau kata sandi tidak cocok.']);

    $this->post('/login', ['username' => $admin->username, 'password' => 'password']);
    $this->assertAuthenticatedAs($admin);
});

it('menampilkan pesan maintenance di halaman masuk', function () {
    $this->get('/login')->assertInertia(fn ($page) => $page->where('maintenance.aktif', false));

    nyalakanMaintenance(['untuk_mahasiswa' => false]);

    $this->get('/login')->assertInertia(fn ($page) => $page
        ->where('maintenance.aktif', true)
        ->where('maintenance.untuk', ['dosen'])
        ->where('maintenance.pesan', 'Perbaikan server.'));
});

it('memakai pesan bawaan bila pesan dikosongkan', function () {
    nyalakanMaintenance(['pesan' => null]);

    expect(PengaturanMaintenance::shared()['pesan'])->toBe(PengaturanMaintenance::PESAN_BAWAAN);
});
