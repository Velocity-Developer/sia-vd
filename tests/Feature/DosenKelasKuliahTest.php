<?php

use App\Models\User;

it('lists only classes taught by the authenticated dosen', function () {
    $kelasMilikDosen = createMateriKelasKuliah();
    $dosen = $kelasMilikDosen->dosen->user;
    $kelasLain = createMateriKelasKuliah();

    $this->actingAs($dosen)
        ->get(route('dosen.kelas-kuliah.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Kelas/KelasKuliahIndex')
            ->where('kelasKuliahs.total', 1)
            ->where('kelasKuliahs.data.0.id', $kelasMilikDosen->id));

    expect($kelasLain->dosen_id)->not->toBe($kelasMilikDosen->dosen_id);
});

it('shows class detail owned by the authenticated dosen', function () {
    $kelas = createMateriKelasKuliah();
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)
        ->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Kelas/KelasKuliahShow')
            ->where('kelasKuliah.id', $kelas->id));
});

it('forbids class detail owned by another dosen', function () {
    $kelasMilikDosen = createMateriKelasKuliah();
    $dosen = $kelasMilikDosen->dosen->user;
    $kelasLain = createMateriKelasKuliah();

    $this->actingAs($dosen)
        ->get(route('dosen.kelas-kuliah.show', $kelasLain))
        ->assertForbidden();
});

it('forbids non-dosen roles from dosen class pages', function () {
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();

    $this->actingAs($mahasiswa)->get(route('dosen.kelas-kuliah.index'))->assertForbidden();
    $this->actingAs($mahasiswa)->get(route('dosen.kelas-kuliah.show', $kelas))->assertForbidden();
});
