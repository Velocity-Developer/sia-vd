<?php

use App\LingkupProdi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\Role;
use App\Models\User;

it('manages a curriculum and its courses', function () {
    $kelas = createMateriKelasKuliah();
    $mk = $kelas->mataKuliah;
    $mkTa = MataKuliah::create(['kode_matkul' => 'TA001', 'nama_matkul' => 'Skripsi', 'sks' => 6, 'semester' => 8, 'jenis' => 'Wajib', 'jenis_penilaian' => MataKuliah::TUGAS_AKHIR, 'prodi_id' => $mk->prodi_id]);
    $mkLain = createMateriKelasKuliah()->mataKuliah;
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.kurikulum.store'), ['prodi_id' => $mk->prodi_id, 'nama' => 'Kurikulum 2024', 'aktif' => true])
        ->assertSessionHasNoErrors();
    $kurikulum = Kurikulum::query()->sole();
    $this->actingAs($admin)->post(route('admin.kurikulum.store'), ['prodi_id' => $mk->prodi_id, 'nama' => 'Kurikulum 2024', 'aktif' => true])
        ->assertSessionHasErrors('nama');

    // MK prodi lain ditolak; MK prodi sendiri masuk dengan semester & sifat dari data MK.
    $this->actingAs($admin)->post(route('admin.kurikulum.mata-kuliah.store', $kurikulum), ['mata_kuliah_ids' => [$mkLain->id]])
        ->assertSessionHasErrors('mata_kuliah_ids.0');
    $this->actingAs($admin)->post(route('admin.kurikulum.mata-kuliah.store', $kurikulum), ['mata_kuliah_ids' => [$mk->id, $mkTa->id]])
        ->assertSessionHas('success');
    expect($kurikulum->mataKuliahs()->get()->pluck('pivot.semester', 'id')->all())->toBe([$mk->id => 1, $mkTa->id => 8]);

    $this->actingAs($admin)->put(route('admin.kurikulum.mata-kuliah.update', [$kurikulum, $mk]), ['semester' => 2, 'jenis' => 'Pilihan'])
        ->assertSessionHas('success');
    expect($kurikulum->mataKuliahs()->whereKey($mk->id)->first()->pivot->only(['semester', 'jenis']))->toBe(['semester' => 2, 'jenis' => 'Pilihan'])
        ->and($mk->fresh()->semester)->toBe(1);

    $this->actingAs($admin)->get(route('admin.kurikulum.show', $kurikulum))
        ->assertInertia(fn ($page) => $page->component('Admin/KurikulumShow')->has('mataKuliah', 2)->has('pilihanMataKuliah', 0));
    $this->actingAs($admin)->get(route('admin.kurikulum.index'))
        ->assertInertia(fn ($page) => $page->where('kurikulum.0.jumlah_mk', 2)->where('kurikulum.0.total_sks', 9));

    // Prodi tidak bisa diganti selama berisi mata kuliah.
    $this->actingAs($admin)->put(route('admin.kurikulum.update', $kurikulum), ['prodi_id' => $mkLain->prodi_id, 'nama' => 'Kurikulum 2024', 'aktif' => false])
        ->assertSessionHasErrors('prodi_id');

    $this->actingAs($admin)->delete(route('admin.kurikulum.mata-kuliah.destroy', [$kurikulum, $mk]))->assertSessionHas('success');
    expect($kurikulum->mataKuliahs()->count())->toBe(1);

    $this->actingAs($admin)->delete(route('admin.kurikulum.destroy', $kurikulum))->assertRedirect(route('admin.kurikulum.index'));
    expect(Kurikulum::query()->count())->toBe(0)->and(MataKuliah::query()->whereKey($mkTa->id)->exists())->toBeTrue();
});

it('limits Prodi accounts to curricula of their program studi', function () {
    $prodiA = createMateriKelasKuliah()->mataKuliah->prodi_id;
    $prodiB = createMateriKelasKuliah()->mataKuliah->prodi_id;
    $lain = Kurikulum::create(['prodi_id' => $prodiB, 'nama' => 'Kurikulum B', 'aktif' => true]);
    $akun = User::factory()->admin()->create(['role_id' => Role::query()->where('slug', LingkupProdi::ROLE)->value('id')]);
    $akun->adminProfile->update(['prodi_id' => $prodiA]);
    $akun = $akun->fresh();

    $this->actingAs($akun)->get(route('admin.kurikulum.index'))->assertInertia(fn ($page) => $page->has('kurikulum', 0)->has('prodiOptions', 1));
    $this->actingAs($akun)->get(route('admin.kurikulum.show', $lain))->assertNotFound();
    $this->actingAs($akun)->post(route('admin.kurikulum.store'), ['prodi_id' => $prodiB, 'nama' => 'Kurikulum X', 'aktif' => true])->assertForbidden();
    $this->actingAs($akun)->post(route('admin.kurikulum.store'), ['prodi_id' => $prodiA, 'nama' => 'Kurikulum A', 'aktif' => true])->assertSessionHasNoErrors();
});
