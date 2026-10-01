<?php

use App\Models\Agama;
use App\Models\BadanHukum;
use App\Models\Kota;
use App\Models\Provinsi;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('mengelola data agama', function () {
    $admin = User::factory()->admin()->create();
    // Migrasi PMB mengisi agama berkode Feeder; tes ini mulai dari daftar kosong.
    Agama::query()->delete();

    $this->actingAs($admin)->post(route('admin.agama.store'), ['kode' => '1', 'nama' => 'Islam'])
        ->assertRedirect(route('admin.agama.index'));
    $agama = Agama::query()->where('kode', '1')->firstOrFail();

    $this->actingAs($admin)->post(route('admin.agama.store'), ['kode' => '1', 'nama' => 'Kristen'])
        ->assertSessionHasErrors('kode');
    $this->actingAs($admin)->post(route('admin.agama.store'), ['kode' => '2', 'nama' => 'Islam'])
        ->assertSessionHasErrors('nama');

    $this->actingAs($admin)->put(route('admin.agama.update', $agama), ['kode' => '1', 'nama' => 'Islam (diubah)'])
        ->assertRedirect(route('admin.agama.index'));
    expect($agama->fresh()->nama)->toBe('Islam (diubah)');

    $this->actingAs($admin)->get(route('admin.agama.index', ['search' => 'diubah']))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Agama')->where('agamas.total', 1));

    $this->actingAs($admin)->delete(route('admin.agama.destroy', $agama))->assertRedirect(route('admin.agama.index'));
    expect(Agama::query()->count())->toBe(0);
});

it('mengelola provinsi dan kota/kabupaten', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.provinsi.store'), ['kode' => '190000', 'nama' => 'Prov. Sulawesi Selatan'])
        ->assertRedirect(route('admin.provinsi.index'));
    $sulsel = Provinsi::query()->where('kode', '190000')->firstOrFail();
    $jabar = Provinsi::query()->create(['kode' => '020000', 'nama' => 'Prov. Jawa Barat']);

    $this->actingAs($admin)->post(route('admin.kota.store'), ['provinsi_id' => $sulsel->id, 'kode' => '196000', 'nama' => 'Kota Makassar'])
        ->assertRedirect(route('admin.kota.index'));
    $makassar = Kota::query()->where('kode', '196000')->firstOrFail();

    $this->actingAs($admin)->post(route('admin.kota.store'), ['provinsi_id' => $sulsel->id, 'kode' => '196001', 'nama' => 'Kota Makassar'])
        ->assertSessionHasErrors('nama');
    $this->actingAs($admin)->post(route('admin.kota.store'), ['provinsi_id' => $jabar->id, 'kode' => '196000', 'nama' => 'Kota Bandung'])
        ->assertSessionHasErrors('kode');
    $this->actingAs($admin)->post(route('admin.kota.store'), ['provinsi_id' => 999, 'kode' => '999999', 'nama' => 'Kota Fiktif'])
        ->assertSessionHasErrors('provinsi_id');
    // Nama sama boleh di provinsi lain.
    $this->actingAs($admin)->post(route('admin.kota.store'), ['provinsi_id' => $jabar->id, 'kode' => '026000', 'nama' => 'Kota Makassar'])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)->get(route('admin.kota.index', ['provinsi_id' => $sulsel->id]))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Kota')->where('kotas.total', 1)->where('kotas.data.0.provinsi.nama', 'Prov. Sulawesi Selatan'));
    $this->actingAs($admin)->get(route('admin.provinsi.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Provinsi')->where('provinsis.total', 2)->where('provinsis.data.0.kotas_count', 1));

    $this->actingAs($admin)->delete(route('admin.provinsi.destroy', $sulsel))->assertSessionHas('error');
    expect($sulsel->fresh())->not->toBeNull();

    $this->actingAs($admin)->delete(route('admin.kota.destroy', $makassar))->assertRedirect(route('admin.kota.index'));
    $this->actingAs($admin)->delete(route('admin.provinsi.destroy', $sulsel))->assertSessionHas('success');
    expect($sulsel->fresh())->toBeNull();
});

it('menolak pengguna tanpa izin master agama dan wilayah', function () {
    $dosen = User::factory()->dosen()->create();

    foreach (['admin.agama.index', 'admin.provinsi.index', 'admin.kota.index'] as $rute) {
        $this->actingAs($dosen)->get(route($rute))->assertForbidden();
    }
});

it('mengelola satu data badan hukum tanpa menambah baris baru', function () {
    $admin = User::factory()->admin()->create();
    $sulsel = Provinsi::query()->create(['kode' => '190000', 'nama' => 'Prov. Sulawesi Selatan']);
    $jabar = Provinsi::query()->create(['kode' => '020000', 'nama' => 'Prov. Jawa Barat']);
    $makassar = Kota::query()->create(['provinsi_id' => $sulsel->id, 'kode' => '196000', 'nama' => 'Kota Makassar']);

    $this->actingAs($admin)->get(route('admin.badan-hukum.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/BadanHukum')->where('badanHukum.id', BadanHukum::SINGLETON_ID));

    $isian = [
        'nama_badan_hukum' => 'Yayasan Pendidikan Yapika',
        'tanggal_berdiri' => '1990-05-01',
        'nomor_akta_terakhir' => '12',
        'tanggal_akta_terakhir' => '2020-01-15',
        'nomor_pengesahan' => 'AHU-0001.AH.01.04.2020',
        'tanggal_pengesahan' => '2020-02-01',
        'alamat_jalan' => 'Jl. Sultan Alauddin No. 98',
        'provinsi_id' => $sulsel->id,
        'kota_id' => $makassar->id,
        'kode_pos' => '90221',
        'telepon' => '0411-883619',
        'faximili' => '0411-883619',
        'email' => 'yayasan@example.or.id',
        'website' => 'https://example.or.id',
    ];

    $this->actingAs($admin)->put(route('admin.badan-hukum.update'), $isian)->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('admin.badan-hukum.update'), [...$isian, 'nama_badan_hukum' => 'Yayasan Yapika'])->assertSessionHasNoErrors();

    expect(BadanHukum::query()->count())->toBe(1);
    $badanHukum = BadanHukum::current();
    expect($badanHukum->nama_badan_hukum)->toBe('Yayasan Yapika')
        ->and($badanHukum->tanggal_pengesahan->format('Y-m-d'))->toBe('2020-02-01')
        ->and($badanHukum->kota_id)->toBe($makassar->id)
        ->and($badanHukum->updated_by)->toBe($admin->id);

    // Kota harus berada di provinsi yang dipilih.
    $this->actingAs($admin)->put(route('admin.badan-hukum.update'), [...$isian, 'provinsi_id' => $jabar->id])
        ->assertSessionHasErrors('kota_id');
    $this->actingAs($admin)->put(route('admin.badan-hukum.update'), [...$isian, 'nama_badan_hukum' => '', 'email' => 'bukan-email'])
        ->assertSessionHasErrors(['nama_badan_hukum', 'email']);

    // Provinsi dan kota yang dipakai badan hukum tidak bisa dihapus.
    $this->actingAs($admin)->delete(route('admin.kota.destroy', $makassar))->assertSessionHas('error');
    expect($makassar->fresh())->not->toBeNull();

    $dosen = User::factory()->dosen()->create();
    $this->actingAs($dosen)->get(route('admin.badan-hukum.edit'))->assertForbidden();
    $this->actingAs($dosen)->put(route('admin.badan-hukum.update'), $isian)->assertForbidden();
});
