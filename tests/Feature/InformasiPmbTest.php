<?php

use App\Models\InformasiPmb;
use App\Models\User;

it('lets admin edit the public PMB information page', function () {
    $this->get(route('pmb.informasi'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Pmb/Informasi')->where('judul', 'Penerimaan Mahasiswa Baru')->has('bagian', 0)->where('periode', null));

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.informasi-pmb.edit'))->assertOk();
    $this->actingAs($admin)->put(route('admin.informasi-pmb.update'), [
        'judul' => 'PMB 2026/2027', 'pengantar' => 'Selamat datang', 'syarat' => "Ijazah SMA\nTranskrip", 'kontak' => 'WA 0812',
    ])->assertSessionHas('success');
    expect(InformasiPmb::current()->syarat)->toBe("Ijazah SMA\nTranskrip");

    auth()->logout();
    $this->get(route('pmb.informasi'))
        ->assertInertia(fn ($page) => $page->where('judul', 'PMB 2026/2027')->has('bagian', 2)->where('bagian.0.judul', 'Syarat Pendaftaran'));

    $this->actingAs(User::factory()->dosen()->create())->get(route('admin.informasi-pmb.edit'))->assertForbidden();
});
