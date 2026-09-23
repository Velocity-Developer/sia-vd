<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MataKuliah;
use App\Models\PengaturanAkademik;
use App\Models\TahunAkademik;
use App\Models\User;

/**
 * Kelas di tahun akademik aktif yang periode KRS-nya sedang berjalan, plus mahasiswa yang berhak mengambilnya.
 */
function krsSetup(int $sks = 3): array
{
    $kelas = createMateriKelasKuliah();
    $kelas->tahunAkademik->update(['tanggal_krs_awal' => now()->subDay(), 'tanggal_krs_akhir' => now()->addDay()]);
    $kelas->mataKuliah->update(['sks' => $sks]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    $mahasiswa->mahasiswaProfile->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'semester' => $kelas->mataKuliah->semester, 'status' => 'Aktif']);

    return [$mahasiswa->fresh(), $kelas->fresh()];
}

function kelasLainDiProdi(KelasKuliah $kelas, int $sks, ?TahunAkademik $tahun = null, int $semester = 1): KelasKuliah
{
    $suffix = bin2hex(random_bytes(3));
    $matkul = MataKuliah::create(['kode_matkul' => "MK{$suffix}", 'nama_matkul' => "Matkul {$suffix}", 'sks' => $sks, 'semester' => $semester, 'jenis' => 'Wajib', 'prodi_id' => $kelas->mataKuliah->prodi_id]);

    return KelasKuliah::create(['kode_kelas' => "K{$suffix}", 'tahun_akademik_id' => ($tahun ?? $kelas->tahunAkademik)->id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $matkul->id]);
}

it('rejects KRS outside the KRS period', function () {
    [$mahasiswa, $kelas] = krsSetup();
    $kelas->tahunAkademik->update(['tanggal_krs_awal' => now()->subDays(10), 'tanggal_krs_akhir' => now()->subDays(3)]);

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))
        ->assertSessionHas('krs_error', 'Periode pengambilan KRS belum dibuka atau sudah berakhir.');

    expect(Krs::count())->toBe(0);
});

it('rejects KRS from a student who is not active', function () {
    [$mahasiswa, $kelas] = krsSetup();
    $mahasiswa->mahasiswaProfile->update(['status' => 'Cuti']);

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))
        ->assertSessionHas('krs_error', fn (string $message): bool => str_contains($message, 'Cuti'));

    expect(Krs::count())->toBe(0);
});

it('rejects KRS that exceeds the SKS limit', function () {
    [$mahasiswa, $kelas] = krsSetup(sks: 6);
    $besar = kelasLainDiProdi($kelas, PengaturanAkademik::current()->maks_sks_tanpa_ips - 3);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $besar->id, 'status' => 'Aktif']);

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))
        ->assertSessionHas('krs_error', fn (string $message): bool => str_contains($message, 'batas maksimal'));

    expect(Krs::where('kelas_id', $kelas->id)->exists())->toBeFalse();
});

it('takes a class within the rules', function () {
    [$mahasiswa, $kelas] = krsSetup();

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))->assertSessionHas('krs_success');

    expect(Krs::where('kelas_id', $kelas->id)->where('mahasiswa_id', $mahasiswa->mahasiswaProfile->id)->exists())->toBeTrue();
});

it('lets a student retake a failed course from a previous year and offers it in the KRS list', function () {
    [$mahasiswa, $kelas] = krsSetup();
    $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Ganjil', 'tanggal_mulai' => '2024-08-01', 'tanggal_akhir' => '2025-01-31', 'tanggal_krs_awal' => '2024-08-01', 'tanggal_krs_akhir' => '2024-08-14', 'status' => false]);
    $kelasLalu = KelasKuliah::create(['kode_kelas' => 'LALU-A', 'tahun_akademik_id' => $lalu->id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id]);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasLalu->id, 'status' => 'Aktif', 'nilai' => 'E']);
    $mahasiswa->mahasiswaProfile->update(['semester' => 3]);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page
            ->where('kelasKuliahs', fn ($kelasList) => collect($kelasList)->pluck('id')->contains($kelas->id))
            ->where('matkulMengulang', fn ($ids) => collect($ids)->contains($kelas->matkul_id)));

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))->assertSessionHas('krs_success');
});

it('does not let a student retake a course already passed', function () {
    [$mahasiswa, $kelas] = krsSetup();
    $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Ganjil', 'tanggal_mulai' => '2024-08-01', 'tanggal_akhir' => '2025-01-31', 'tanggal_krs_awal' => '2024-08-01', 'tanggal_krs_akhir' => '2024-08-14', 'status' => false]);
    $kelasLalu = KelasKuliah::create(['kode_kelas' => 'LALU-A', 'tahun_akademik_id' => $lalu->id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id]);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasLalu->id, 'status' => 'Aktif', 'nilai' => 'B']);

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))
        ->assertSessionHas('krs_error', fn (string $message): bool => str_contains($message, 'sudah lulus'));
});

it('lets a student cancel an ungraded class during the KRS period only', function () {
    [$mahasiswa, $kelas] = krsSetup();
    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

    $this->actingAs($mahasiswa)->delete(route('mahasiswa.krs.destroy', $krs))->assertSessionHas('krs_success');
    expect(Krs::find($krs->id))->toBeNull();

    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    $kelas->tahunAkademik->update(['tanggal_krs_awal' => now()->subDays(10), 'tanggal_krs_akhir' => now()->subDays(3)]);

    $this->actingAs($mahasiswa)->delete(route('mahasiswa.krs.destroy', $krs))->assertSessionHas('krs_error');
    expect(Krs::find($krs->id))->not->toBeNull();
});

it('does not let a student cancel another student\'s KRS', function () {
    [$mahasiswa, $kelas] = krsSetup();
    $lain = User::factory()->mahasiswa()->create();
    $krs = Krs::create(['mahasiswa_id' => $lain->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

    $this->actingAs($mahasiswa)->delete(route('mahasiswa.krs.destroy', $krs))->assertForbidden();
});

it('lets admin cancel an ungraded KRS but not a graded one', function () {
    $admin = User::factory()->admin()->create();
    [$mahasiswa, $kelas] = krsSetup();
    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

    $krs->update(['nilai' => 'A']);
    $this->actingAs($admin)->delete(route('admin.kelas-kuliah.krs.destroy', [$kelas, $krs]))->assertSessionHas('error');
    expect(Krs::find($krs->id))->not->toBeNull();

    $krs->update(['nilai' => null]);
    $this->actingAs($admin)->delete(route('admin.kelas-kuliah.krs.destroy', [$kelas, $krs]))->assertSessionHas('success');
    expect(Krs::find($krs->id))->toBeNull();
});

it('uses the previous semester IPS and falls back while its grades are incomplete', function () {
    [$mahasiswa, $kelas] = krsSetup();
    $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Genap', 'tanggal_mulai' => '2025-02-01', 'tanggal_akhir' => '2025-07-31', 'tanggal_krs_awal' => '2025-02-01', 'tanggal_krs_akhir' => '2025-02-14', 'status' => false]);
    $kelasLalu = fn (string $kode) => KelasKuliah::create(['kode_kelas' => $kode, 'tahun_akademik_id' => $lalu->id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => kelasLainDiProdi($kelas, 3, $lalu)->matkul_id]);
    $profil = $mahasiswa->mahasiswaProfile;
    Krs::create(['mahasiswa_id' => $profil->id, 'kelas_id' => $kelasLalu('LALU-A')->id, 'nilai' => 'A']);
    $belumDinilai = Krs::create(['mahasiswa_id' => $profil->id, 'kelas_id' => $kelasLalu('LALU-B')->id]);

    // Selama masih ada nilai yang belum masuk, batas SKS memakai angka "tanpa IPS".
    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where('ipsSebelumnya', null)->where('maksSks', PengaturanAkademik::current()->maks_sks_tanpa_ips));

    $belumDinilai->update(['nilai' => 'A']);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where('ipsSebelumnya.ips', 4)->where('maksSks', 24));
});
