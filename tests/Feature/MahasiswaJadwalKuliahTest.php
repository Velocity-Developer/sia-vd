<?php

use App\Models\Jadwal;
use App\Models\Krs;
use App\Models\Ruang;
use App\Models\TahunAkademik;
use App\Models\User;

it('shows schedules only from mahasiswa KRS', function () {
    $takenClass = createMateriKelasKuliah();
    $otherClass = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();

    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $takenClass->id]);
    $ruang = Ruang::create(['kode_ruang' => 'R-101', 'nama_ruang' => 'Ruang 101', 'kapasitas' => 30]);
    Jadwal::create(['kelas_id' => $takenClass->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $ruang->id]);
    Jadwal::create(['kelas_id' => $otherClass->id, 'hari' => 'Selasa', 'jam_mulai' => '10:00', 'jam_akhir' => '12:00', 'ruang_id' => $ruang->id]);

    $this->actingAs($mahasiswa)
        ->get('/mahasiswa/jadwal')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Mahasiswa/JadwalKuliah')
            ->has('kelasKuliahs', 1)
            ->where('kelasKuliahs.0.id', $takenClass->id)
            ->where('kelasKuliahs.0.jadwals.0.hari', 'Senin'));
});

it('hides schedules from inactive academic years', function () {
    $activeClass = createMateriKelasKuliah();
    $pastTahunAkademik = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Ganjil', 'tanggal_mulai' => '2024-08-01', 'tanggal_akhir' => '2025-01-31', 'tanggal_krs_awal' => '2024-08-01', 'tanggal_krs_akhir' => '2024-08-14', 'status' => false]);
    $pastClass = createMateriKelasKuliah($pastTahunAkademik);
    $mahasiswa = User::factory()->mahasiswa()->create();

    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $activeClass->id]);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $pastClass->id]);

    $this->actingAs($mahasiswa)
        ->get('/mahasiswa/jadwal')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Mahasiswa/JadwalKuliah')
            ->has('kelasKuliahs', 1)
            ->where('kelasKuliahs.0.id', $activeClass->id));
});

it('shows enrolled class detail and forbids other classes', function () {
    $takenClass = createMateriKelasKuliah();
    $otherClass = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();

    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $takenClass->id]);

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.jadwal-kuliah.show', $takenClass))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Mahasiswa/KelasKuliahShow')
            ->where('kelasKuliah.id', $takenClass->id));

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.jadwal-kuliah.show', $otherClass))
        ->assertForbidden();
});
