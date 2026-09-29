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
    $mahasiswa->mahasiswaProfile->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'angkatan' => angkatanUntuk($kelas), 'status' => 'Aktif']);

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

it('offers no classes to a student on leave or graduated', function (string $status) {
    [$mahasiswa] = krsSetup();
    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))->assertInertia(fn ($page) => $page->has('kelasKuliahs', 1));

    $mahasiswa->mahasiswaProfile->update(['status' => $status]);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->has('kelasKuliahs', 0)->where('bolehKrs', false));
})->with(['Cuti', 'Lulus']);

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
    $mahasiswa->mahasiswaProfile->update(['angkatan' => angkatanUntuk($kelas, 3)]);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page
            ->where('kelasKuliahs', fn ($kelasList) => collect($kelasList)->pluck('id')->contains($kelas->id))
            ->where("labelMatkul.{$kelas->matkul_id}", 'Mengulang'));

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

it('offers postponed courses of the same parity until they are taken', function () {
    [$mahasiswa, $kelas] = krsSetup();
    $mahasiswa->mahasiswaProfile->update(['angkatan' => angkatanUntuk($kelas, 5)]);
    $semesterIni = kelasLainDiProdi($kelas, 2, semester: 5);
    $tertunda = kelasLainDiProdi($kelas, 2, semester: 3);
    $paritasLain = kelasLainDiProdi($kelas, 2, semester: 4);
    $semesterDepan = kelasLainDiProdi($kelas, 2, semester: 7);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page
            ->where('mahasiswa.semester', 5)
            ->where('kelasKuliahs', fn ($kelasList) => collect($kelasList)->pluck('id')->sort()->values()->all() === collect([$kelas->id, $semesterIni->id, $tertunda->id])->sort()->values()->all())
            ->where("labelMatkul.{$tertunda->matkul_id}", 'Tertunda smt 3')
            ->where("labelMatkul.{$kelas->matkul_id}", 'Tertunda smt 1')
            ->missing("labelMatkul.{$semesterIni->matkul_id}"));

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $paritasLain))
        ->assertSessionHas('krs_error', 'Mata kuliah ini tidak ditawarkan untuk semester Anda.');
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $semesterDepan))
        ->assertSessionHas('krs_error', 'Mata kuliah ini tidak ditawarkan untuk semester Anda.');

    // Setelah diambil, kelas tertunda tetap tampil agar bisa dibatalkan.
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $tertunda))->assertSessionHas('krs_success');
    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where('kelasKuliahs', fn ($kelasList) => collect($kelasList)->pluck('id')->contains($tertunda->id)));
});

it('no longer offers a postponed course once it has been passed', function () {
    [$mahasiswa, $kelas] = krsSetup();
    $mahasiswa->mahasiswaProfile->update(['angkatan' => angkatanUntuk($kelas, 3)]);
    $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Ganjil', 'tanggal_mulai' => '2024-08-01', 'tanggal_akhir' => '2025-01-31', 'tanggal_krs_awal' => '2024-08-01', 'tanggal_krs_akhir' => '2024-08-14', 'status' => false]);
    $kelasLalu = KelasKuliah::create(['kode_kelas' => 'LALU-A', 'tahun_akademik_id' => $lalu->id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id]);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasLalu->id, 'status' => 'Aktif', 'nilai' => 'A']);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where('kelasKuliahs', fn ($kelasList) => ! collect($kelasList)->pluck('id')->contains($kelas->id)));
});

/**
 * Mahasiswa semester 5 dengan mata kuliah semester 5 yang mensyaratkan mata kuliah semester 3.
 *
 * @return array{0: User, 1: KelasKuliah, 2: KelasKuliah, 3: TahunAkademik}
 */
function krsDenganPrasyarat(): array
{
    [$mahasiswa, $kelas] = krsSetup();
    $mahasiswa->mahasiswaProfile->update(['angkatan' => angkatanUntuk($kelas, 5)]);
    $prasyarat = kelasLainDiProdi($kelas, 2, semester: 3);
    $prasyarat->mataKuliah->update(['nama_matkul' => 'Struktur Data']);
    $lanjutan = kelasLainDiProdi($kelas, 2, semester: 5);
    $lanjutan->mataKuliah->prasyarat()->attach($prasyarat->matkul_id);
    $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Ganjil', 'tanggal_mulai' => '2024-08-01', 'tanggal_akhir' => '2025-01-31', 'tanggal_krs_awal' => '2024-08-01', 'tanggal_krs_akhir' => '2024-08-14', 'status' => false]);

    return [$mahasiswa, $prasyarat, $lanjutan, $lalu];
}

it('shows a course with an unmet prerequisite as locked and refuses it', function () {
    [$mahasiswa, $prasyarat, $lanjutan] = krsDenganPrasyarat();

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page
            ->where('kelasKuliahs', fn ($kelasList) => collect($kelasList)->pluck('id')->contains($lanjutan->id))
            ->where("terkunciMatkul.{$lanjutan->matkul_id}", 'Prasyarat: Struktur Data belum lulus'));

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $lanjutan))
        ->assertSessionHas('krs_error', 'Prasyarat: Struktur Data belum lulus');

    // Prasyarat yang baru diambil semester ini belum bernilai, jadi lanjutannya tetap terkunci.
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $prasyarat))->assertSessionHas('krs_success');
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $lanjutan))
        ->assertSessionHas('krs_error', 'Prasyarat: Struktur Data belum lulus');
});

it('accepts a passed prerequisite even when the grade may still be retaken', function (string $nilai, bool $boleh) {
    [$mahasiswa, $prasyarat, $lanjutan, $lalu] = krsDenganPrasyarat();
    $kelasLalu = KelasKuliah::create(['kode_kelas' => 'LALU-P', 'tahun_akademik_id' => $lalu->id, 'kapasitas' => 30, 'dosen_id' => $prasyarat->dosen_id, 'matkul_id' => $prasyarat->matkul_id]);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasLalu->id, 'status' => 'Aktif', 'nilai' => $nilai]);

    $respons = $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $lanjutan));

    $boleh ? $respons->assertSessionHas('krs_success') : $respons->assertSessionHas('krs_error', 'Prasyarat: Struktur Data belum lulus');
})->with([
    'D lulus tapi boleh diulang' => ['D', true],
    'E tidak lulus' => ['E', false],
]);
