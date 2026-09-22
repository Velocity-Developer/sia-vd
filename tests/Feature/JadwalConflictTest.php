<?php

use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MataKuliah;
use App\Models\Ruang;
use App\Models\TahunAkademik;
use App\Models\User;

function jadwalPayload(Ruang $ruang, array $overrides = []): array
{
    return ['hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $ruang->id, ...$overrides];
}

it('ignores room bookings from another tahun akademik', function () {
    $admin = User::factory()->admin()->create();
    $ruang = Ruang::create(['kode_ruang' => 'R-101', 'nama_ruang' => 'Ruang 101', 'kapasitas' => 30]);
    $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Genap', 'tanggal_mulai' => '2025-02-01', 'tanggal_akhir' => '2025-07-31', 'tanggal_krs_awal' => '2025-02-01', 'tanggal_krs_akhir' => '2025-02-14', 'status' => false]);
    $kelasLalu = createMateriKelasKuliah($lalu);
    Jadwal::create(['kelas_id' => $kelasLalu->id, ...jadwalPayload($ruang)]);
    $kelas = createMateriKelasKuliah();

    $this->actingAs($admin)
        ->post(route('admin.kelas-kuliah.jadwal.store', $kelas), jadwalPayload($ruang))
        ->assertSessionHasNoErrors();

    expect($kelas->jadwals()->count())->toBe(1);
});

it('still rejects a room booked in the same tahun akademik', function () {
    $admin = User::factory()->admin()->create();
    $ruang = Ruang::create(['kode_ruang' => 'R-101', 'nama_ruang' => 'Ruang 101', 'kapasitas' => 30]);
    $lain = createMateriKelasKuliah();
    Jadwal::create(['kelas_id' => $lain->id, ...jadwalPayload($ruang)]);
    $kelas = createMateriKelasKuliah();

    $this->actingAs($admin)
        ->post(route('admin.kelas-kuliah.jadwal.store', $kelas), jadwalPayload($ruang, ['jam_mulai' => '09:00', 'jam_akhir' => '11:00']))
        ->assertSessionHasErrors('ruang_id');
});

it('rejects a schedule that clashes with another class of the same dosen', function () {
    $admin = User::factory()->admin()->create();
    $ruangA = Ruang::create(['kode_ruang' => 'R-101', 'nama_ruang' => 'Ruang 101', 'kapasitas' => 30]);
    $ruangB = Ruang::create(['kode_ruang' => 'R-102', 'nama_ruang' => 'Ruang 102', 'kapasitas' => 30]);
    $kelas = createMateriKelasKuliah();
    $kelasLain = createMateriKelasKuliah();
    $kelasLain->update(['dosen_id' => $kelas->dosen_id]);
    Jadwal::create(['kelas_id' => $kelasLain->id, ...jadwalPayload($ruangA)]);

    $this->actingAs($admin)
        ->post(route('admin.kelas-kuliah.jadwal.store', $kelas), jadwalPayload($ruangB))
        ->assertSessionHasErrors('jam_mulai');

    expect($kelas->jadwals()->count())->toBe(0);
});

it('rejects a KRS class whose schedule clashes with a class already taken', function () {
    $ruangA = Ruang::create(['kode_ruang' => 'R-101', 'nama_ruang' => 'Ruang 101', 'kapasitas' => 30]);
    $ruangB = Ruang::create(['kode_ruang' => 'R-102', 'nama_ruang' => 'Ruang 102', 'kapasitas' => 30]);
    $diambil = createMateriKelasKuliah();
    $diambil->tahunAkademik->update(['tanggal_krs_awal' => now()->subDay(), 'tanggal_krs_akhir' => now()->addDay()]);
    $prodiId = $diambil->mataKuliah->prodi_id;
    $matkulLain = MataKuliah::create(['kode_matkul' => 'IF-LAIN', 'nama_matkul' => 'Basis Data', 'sks' => 3, 'semester' => 1, 'jenis' => 'Wajib', 'prodi_id' => $prodiId]);
    $target = KelasKuliah::create(['kode_kelas' => 'BD-A', 'tahun_akademik_id' => $diambil->tahun_akademik_id, 'kapasitas' => 30, 'dosen_id' => $diambil->dosen_id, 'matkul_id' => $matkulLain->id]);
    Jadwal::create(['kelas_id' => $diambil->id, ...jadwalPayload($ruangA)]);
    Jadwal::create(['kelas_id' => $target->id, ...jadwalPayload($ruangB, ['jam_mulai' => '09:00', 'jam_akhir' => '11:00'])]);

    $mahasiswa = User::factory()->mahasiswa()->create();
    $mahasiswa->mahasiswaProfile->update(['prodi_id' => $prodiId, 'semester' => 1]);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $diambil->id, 'status' => 'Aktif']);

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.krs.store', $target))
        ->assertSessionHas('krs_error', fn (string $message): bool => str_contains($message, 'bentrok'));

    expect(Krs::where('kelas_id', $target->id)->exists())->toBeFalse();
});
