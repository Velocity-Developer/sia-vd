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
