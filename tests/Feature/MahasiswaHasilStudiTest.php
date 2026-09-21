<?php

use App\Models\Krs;
use App\Models\TahunAkademik;
use App\Models\User;
use App\Role;

it('shows student study results filtered by selected academic year', function () {
    $this->seed();

    $mahasiswa = User::where('role', Role::Mahasiswa->value)->first();
    $profil = $mahasiswa->mahasiswaProfile;
    $tahunAkademik = TahunAkademik::where('tahun', '2023/2024')->where('semester', 'Ganjil')->first();
    $expectedCount = Krs::where('mahasiswa_id', $profil->id)->whereHas('kelasKuliah', fn ($query) => $query->where('tahun_akademik_id', $tahunAkademik->id))->count();

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.hasil-studi', ['tahun_akademik_id' => $tahunAkademik->id]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Mahasiswa/HasilStudi')
            ->where('tahunAkademikTerpilih', $tahunAkademik->id)
            ->has('krs', $expectedCount)
            ->has('tahunAkademiks', 5)
        );
});

it('defaults study results to active academic year', function () {
    $this->seed();

    $mahasiswa = User::where('role', Role::Mahasiswa->value)->first();
    $activeYear = TahunAkademik::where('status', true)->first();

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.hasil-studi'))
        ->assertInertia(fn ($page) => $page->where('tahunAkademikTerpilih', $activeYear->id));
});

it('downloads study result card as pdf for the selected academic year', function () {
    $this->seed();

    $mahasiswa = User::where('role', Role::Mahasiswa->value)->first();
    $tahunAkademik = TahunAkademik::where('tahun', '2023/2024')->where('semester', 'Ganjil')->first();

    $response = $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.hasil-studi.download', ['tahun_akademik_id' => $tahunAkademik->id]))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain("khs-{$mahasiswa->mahasiswaProfile->nim}-2023-2024-Ganjil.pdf");
});

it('forbids study result card download for user without student profile', function () {
    $this->seed();

    $admin = User::where('role', Role::Admin->value)->first();

    $this->actingAs($admin)
        ->get(route('mahasiswa.hasil-studi.download'))
        ->assertForbidden();
});
