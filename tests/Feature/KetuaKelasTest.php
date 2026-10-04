<?php

use App\Models\Krs;
use App\Models\User;

it('sets a participant as the class leader', function () {
    $kelas = createMateriKelasKuliah();
    $peserta = User::factory()->mahasiswa()->create()->mahasiswaProfile;
    Krs::create(['mahasiswa_id' => $peserta->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    $bukanPeserta = User::factory()->mahasiswa()->create()->mahasiswaProfile;
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.ketua-kelas.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/KetuaKelas')->where('kelas.data.0.peserta.0.id', $peserta->id));

    $this->actingAs($admin)->put(route('admin.ketua-kelas.update', $kelas), ['ketua_kelas_id' => $bukanPeserta->id])->assertSessionHasErrors('ketua_kelas_id');
    $this->actingAs($admin)->put(route('admin.ketua-kelas.update', $kelas), ['ketua_kelas_id' => $peserta->id])->assertSessionHas('success');
    expect($kelas->fresh()->ketua_kelas_id)->toBe($peserta->id);

    $this->actingAs($admin)->put(route('admin.ketua-kelas.update', $kelas), ['ketua_kelas_id' => null])->assertSessionHas('success');
    expect($kelas->fresh()->ketua_kelas_id)->toBeNull();
});
