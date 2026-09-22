<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\TahunAkademik;
use App\Models\User;

it('lets a grade be cleared', function () {
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'B']);

    $this->actingAs($kelas->dosen->user)
        ->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => null])
        ->assertSessionHas('success');

    expect($krs->fresh()->nilai)->toBeNull();
});

it('locks grades for dosen once the tahun akademik is no longer active, but not for admin', function () {
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'B']);
    $kelas->tahunAkademik->update(['status' => false]);

    $this->actingAs($kelas->dosen->user)
        ->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'A'])
        ->assertSessionHas('error');
    expect($krs->fresh()->nilai)->toBe('B');

    $this->actingAs($kelas->dosen->user)
        ->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('nilaiTerkunci', true));

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'A'])
        ->assertSessionHas('success');
    expect($krs->fresh()->nilai)->toBe('A');
});

it('counts a retaken course once in the transcript using its best grade', function () {
    $kelas = createMateriKelasKuliah();
    $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Ganjil', 'tanggal_mulai' => '2024-08-01', 'tanggal_akhir' => '2025-01-31', 'tanggal_krs_awal' => '2024-08-01', 'tanggal_krs_akhir' => '2024-08-14', 'status' => false]);
    $kelasLalu = KelasKuliah::create(['kode_kelas' => 'LALU-A', 'tahun_akademik_id' => $lalu->id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasLalu->id, 'nilai' => 'E']);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'B']);
    $sks = $kelas->mataKuliah->sks;

    $this->actingAs($mahasiswa)->get(route('mahasiswa.transkrip'))
        ->assertInertia(fn ($page) => $page
            ->has('transkrip', 1)
            ->where('transkrip.0.nilai', 'B')
            ->where('transkrip.0.diambil', 2)
            ->where('ringkasan.totalSks', $sks)
            ->where('ringkasan.totalSksLulus', $sks)
            ->where('ringkasan.ipk', 3));
});
