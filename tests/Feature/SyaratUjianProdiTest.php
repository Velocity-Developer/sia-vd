<?php

use App\LingkupProdi;
use App\Models\PengaturanAkademik;
use App\Models\Role;
use App\Models\SyaratUjianProdi;
use App\Models\User;

$isian = ['syarat_ujian_aktif' => true, 'min_kehadiran_ujian' => 80, 'izin_sakit_dihitung_hadir' => true, 'huruf_maks_remidi' => 'C'];

it('lets admin save the general and per program studi exam requirements', function () use ($isian) {
    $kelas = createMateriKelasKuliah();
    $prodiId = $kelas->mataKuliah->prodi_id;
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.syarat-ujian.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/SyaratUjian')->where('bolehUmum', true)->where('prodiId', null));

    $this->actingAs($admin)->put(route('admin.syarat-ujian.update-umum'), [...$isian, 'min_kehadiran_ujian' => 60])->assertSessionHas('success');
    expect(PengaturanAkademik::current()->only(['syarat_ujian_aktif', 'min_kehadiran_ujian', 'izin_sakit_dihitung_hadir', 'huruf_maks_remidi']))
        ->toBe(['syarat_ujian_aktif' => true, 'min_kehadiran_ujian' => 60, 'izin_sakit_dihitung_hadir' => true, 'huruf_maks_remidi' => 'C']);

    // Prodi yang belum diatur menampilkan pengaturan umum.
    $this->actingAs($admin)->get(route('admin.syarat-ujian.index', ['prodi' => $prodiId]))
        ->assertInertia(fn ($page) => $page->where('belumDiatur', true)->where('pengaturan.min_kehadiran_ujian', 60));

    $this->actingAs($admin)->put(route('admin.syarat-ujian.update', $prodiId), [...$isian, 'huruf_maks_remidi' => null])->assertSessionHas('success');
    expect(PengaturanAkademik::untukProdi($prodiId)->min_kehadiran_ujian)->toBe(80)
        ->and(PengaturanAkademik::untukProdi($prodiId)->huruf_maks_remidi)->toBeNull()
        ->and(PengaturanAkademik::untukProdi(null)->min_kehadiran_ujian)->toBe(60);

    $this->actingAs($admin)->put(route('admin.syarat-ujian.update', $prodiId), [...$isian, 'min_kehadiran_ujian' => 101])->assertSessionHasErrors('min_kehadiran_ujian');

    $this->actingAs($admin)->delete(route('admin.syarat-ujian.destroy', $prodiId))->assertSessionHas('success');
    expect(SyaratUjianProdi::query()->count())->toBe(0)
        ->and(PengaturanAkademik::untukProdi($prodiId)->min_kehadiran_ujian)->toBe(60);
});

it('limits Prodi accounts to their own program studi', function () use ($isian) {
    $prodiA = createMateriKelasKuliah()->mataKuliah->prodi_id;
    $prodiB = createMateriKelasKuliah()->mataKuliah->prodi_id;
    $prodi = User::factory()->admin()->create(['role_id' => Role::query()->where('slug', LingkupProdi::ROLE)->value('id')]);
    $prodi->adminProfile->update(['prodi_id' => $prodiA]);
    $prodi = $prodi->fresh();

    $this->actingAs($prodi)->get(route('admin.syarat-ujian.index'))
        ->assertInertia(fn ($page) => $page->where('bolehUmum', false)->where('prodiId', $prodiA)->has('prodi', 1));

    $this->actingAs($prodi)->put(route('admin.syarat-ujian.update-umum'), $isian)->assertForbidden();
    $this->actingAs($prodi)->put(route('admin.syarat-ujian.update', $prodiB), $isian)->assertNotFound();
    $this->actingAs($prodi)->put(route('admin.syarat-ujian.update', $prodiA), $isian)->assertSessionHas('success');

    expect(SyaratUjianProdi::query()->withoutGlobalScopes()->pluck('prodi_id')->all())->toBe([$prodiA]);
});
