<?php

use App\Models\PengaturanInstitusi;
use App\Models\User;

it('membagikan data institusi terbaru ke halaman berikutnya', function () {
    // Data institusi disimpan di cache dan dibagikan ke semua halaman; kalau cache-nya
    // tidak ikut dibuang, nama dan logo lama masih tampil sampai halaman dimuat ulang.
    $admin = User::factory()->admin()->create();
    PengaturanInstitusi::current();

    $this->actingAs($admin)->put(route('institusi.update'), [
        'nama_pt' => 'Universitas Uji Coba',
        'singkatan' => 'UUC',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('institusi.nama_pt', 'Universitas Uji Coba')->where('institusi.singkatan', 'UUC'));
});

it('menolak pengguna tanpa izin mengubah institusi', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();

    $this->actingAs($mahasiswa)->put(route('institusi.update'), ['nama_pt' => 'Kampus Lain'])->assertForbidden();
});

it('menyimpan zona waktu dan memakainya di permintaan berikutnya', function () {
    $admin = User::factory()->admin()->create();
    PengaturanInstitusi::current();

    $this->actingAs($admin)->put(route('institusi.update'), [
        'nama_pt' => 'Universitas Uji Coba',
        'zona_waktu' => 'Asia/Jayapura',
    ])->assertSessionHasNoErrors();

    expect(PengaturanInstitusi::current()->zona_waktu)->toBe('Asia/Jayapura');

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('institusi.zona_waktu', 'Asia/Jayapura')->where('institusi.zona_singkatan', 'WIT'));

    expect(config('app.timezone'))->toBe('Asia/Jayapura')
        ->and(date_default_timezone_get())->toBe('Asia/Jayapura')
        ->and(now()->getTimezone()->getName())->toBe('Asia/Jayapura')
        ->and(PengaturanInstitusi::singkatanZona())->toBe('WIT');
});

it('menolak zona waktu di luar WIB, WITA, dan WIT', function () {
    $admin = User::factory()->admin()->create();
    PengaturanInstitusi::current();

    $this->actingAs($admin)->put(route('institusi.update'), [
        'nama_pt' => 'Universitas Uji Coba',
        'zona_waktu' => 'Europe/London',
    ])->assertSessionHasErrors('zona_waktu');

    expect(PengaturanInstitusi::current()->zona_waktu)->toBe('Asia/Jakarta');
});

it('memakai WIB bila zona waktu belum pernah diatur', function () {
    $this->get(route('login'))->assertSuccessful();

    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(PengaturanInstitusi::singkatanZona())->toBe('WIB');
});
