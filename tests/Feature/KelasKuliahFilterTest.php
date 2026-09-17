<?php

use App\Models\KelasKuliah;
use App\Models\TahunAkademik;
use App\Models\User;
use App\Role;

function tahunAkademikAktif(bool $status): TahunAkademik
{
    return TahunAkademik::create([
        'tahun' => $status ? '2025/2026' : '2024/2025',
        'semester' => 'Ganjil',
        'tanggal_mulai' => $status ? '2025-08-01' : '2024-08-01',
        'tanggal_akhir' => $status ? '2026-01-31' : '2025-01-31',
        'status' => $status,
    ]);
}

function kelasKuliahDenganTahun(TahunAkademik $tahunAkademik, string $kodeKelas, KelasKuliah $referensi): KelasKuliah
{
    return KelasKuliah::create([
        'kode_kelas' => $kodeKelas,
        'tahun_akademik_id' => $tahunAkademik->id,
        'kapasitas' => 30,
        'dosen_id' => $referensi->dosen_id,
        'matkul_id' => $referensi->matkul_id,
    ]);
}

it('defaults the admin class list to the active academic year', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $aktif = tahunAkademikAktif(true);
    $nonaktif = tahunAkademikAktif(false);

    $referensi = createMateriKelasKuliah();
    $kelasAktif = kelasKuliahDenganTahun($aktif, 'AKTIF-A', $referensi);
    kelasKuliahDenganTahun($nonaktif, 'LAMA-A', $referensi);

    $this->actingAs($admin)
        ->get(route('admin.kelas-kuliah.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/KelasKuliah')
            ->where('tahunAkademikId', $aktif->id)
            ->where('mataKuliahId', null)
            ->has('mataKuliahOptions', 1)
            ->where('kelasKuliahs.total', 1)
            ->where('kelasKuliahs.data.0.id', $kelasAktif->id));
});

it('shows every academic year when the admin picks all', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $aktif = tahunAkademikAktif(true);
    $nonaktif = tahunAkademikAktif(false);

    $referensi = createMateriKelasKuliah();
    $referensi->update(['tahun_akademik_id' => $nonaktif->id]);
    kelasKuliahDenganTahun($aktif, 'AKTIF-A', $referensi);

    $this->actingAs($admin)
        ->get(route('admin.kelas-kuliah.index', ['tahun_akademik_id' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tahunAkademikId', null)
            ->where('mataKuliahId', null)
            ->has('mataKuliahOptions', 1)
            ->where('kelasKuliahs.total', 2));
});

it('filters the admin class list by a single class', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $aktif = tahunAkademikAktif(true);
    $nonaktif = tahunAkademikAktif(false);

    $referensi = createMateriKelasKuliah();
    $referensiLain = createMateriKelasKuliah();
    kelasKuliahDenganTahun($aktif, 'AKTIF-A', $referensi);
    $kelasLama = kelasKuliahDenganTahun($nonaktif, 'LAMA-A', $referensiLain);

    $this->actingAs($admin)
        ->get(route('admin.kelas-kuliah.index', ['tahun_akademik_id' => 'all', 'mata_kuliah_id' => $referensiLain->matkul_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('mataKuliahId', $referensiLain->matkul_id)
            ->where('kelasKuliahs.total', 2)
            ->where('kelasKuliahs.data.1.id', $kelasLama->id));
});

it('defaults the dosen class list to the active academic year for own classes only', function () {
    $aktif = tahunAkademikAktif(true);
    $nonaktif = tahunAkademikAktif(false);

    $referensi = createMateriKelasKuliah();
    $dosen = $referensi->dosen->user;
    $kelasAktif = kelasKuliahDenganTahun($aktif, 'AKTIF-A', $referensi);
    kelasKuliahDenganTahun($nonaktif, 'LAMA-A', $referensi);

    $kelasDosenLain = createMateriKelasKuliah();
    $kelasDosenLain->update(['tahun_akademik_id' => $aktif->id]);

    $this->actingAs($dosen)
        ->get(route('dosen.kelas-kuliah.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dosen/KelasKuliah')
            ->where('tahunAkademikId', $aktif->id)
            ->where('mataKuliahId', null)
            ->has('mataKuliahOptions', 1)
            ->where('kelasKuliahs.total', 1)
            ->where('kelasKuliahs.data.0.id', $kelasAktif->id));
});

it('filters the dosen class list by a single class', function () {
    $aktif = tahunAkademikAktif(true);

    $referensi = createMateriKelasKuliah();
    $dosen = $referensi->dosen->user;
    $kelasAktif = kelasKuliahDenganTahun($aktif, 'AKTIF-A', $referensi);
    $kelasLain = kelasKuliahDenganTahun($aktif, 'AKTIF-B', $referensi);

    $this->actingAs($dosen)
        ->get(route('dosen.kelas-kuliah.index', ['mata_kuliah_id' => $kelasLain->matkul_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('mataKuliahId', $kelasLain->matkul_id)
            ->where('kelasKuliahs.total', 2)
            ->where('kelasKuliahs.data.1.id', $kelasLain->id));

    expect($kelasAktif->id)->not->toBe($kelasLain->id);
});
