<?php

use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\User;

/**
 * Satu mahasiswa dengan dua kelas di dua tahun akademik berbeda (sama seperti KRS lintas semester).
 *
 * @return array{0: MahasiswaProfile, 1: list<Krs>, 2: User}
 */
function pendataanNilaiSetup(): array
{
    $kelasA = createMateriKelasKuliah();
    $kelasB = createMateriKelasKuliah();
    $mhs = User::factory()->mahasiswa()->create()->mahasiswaProfile;
    $mhs->update(['prodi_id' => $kelasA->mataKuliah->prodi_id, 'nim' => '2301B001', 'angkatan' => 2023]);

    return [$mhs->fresh(), [
        Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelasA->id, 'status' => 'Aktif']),
        Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelasB->id, 'status' => 'Aktif']),
    ], User::factory()->admin()->create()];
}

it('lists students and every course they took', function () {
    [$mhs, $krs, $admin] = pendataanNilaiSetup();

    $this->actingAs($admin)->get(route('admin.pendataan-nilai.index', ['search' => '2301B']))
        ->assertInertia(fn ($page) => $page->component('Admin/PendataanNilai')->where('mahasiswa.total', 1)
            ->where('mahasiswa.data.0.nim', '2301B001')->where('mahasiswa.data.0.angkatan', 2023));

    $this->actingAs($admin)->get(route('admin.pendataan-nilai.show', $mhs))
        ->assertInertia(fn ($page) => $page->component('Admin/PendataanNilaiMahasiswa')->has('nilai', 2)
            ->where('nilai.0.huruf', null)->where('nilai.0.status', 'belum'));
});

it('shows the angka-to-huruf conversion and validation status read-only', function () {
    [$mhs, $krs, $admin] = pendataanNilaiSetup();
    $krs[0]->update(['nilai' => 'C', 'nilai_angka' => 60]);
    $krs[1]->update(['nilai' => 'B', 'nilai_angka' => 75, 'nilai_divalidasi_at' => now(), 'nilai_divalidasi_oleh' => $admin->id]);

    $this->actingAs($admin)->get(route('admin.pendataan-nilai.show', $mhs))
        ->assertInertia(fn ($page) => $page
            ->where('nilai.0.nilai_angka', 60)->where('nilai.0.huruf', 'C')->where('nilai.0.sumber', 'Konversi angka')->where('nilai.0.status', 'menunggu')
            ->where('nilai.1.status', 'tervalidasi'));

    // Tidak ada lagi isian huruf manual.
    $this->actingAs($admin)->put("/admin/pendataan-nilai/{$mhs->id}/{$krs[0]->id}", ['nilai' => 'A'])->assertNotFound();
    expect($krs[0]->fresh()->nilai)->toBe('C');
});

it('keeps Pendataan Nilai Akhir for permitted admins', function () {
    [$mhs, $krs] = pendataanNilaiSetup();
    $dosen = $krs[0]->kelasKuliah->dosen->user;

    $this->actingAs($dosen)->get(route('admin.pendataan-nilai.index'))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.pendataan-nilai.show', $mhs))->assertForbidden();
});
