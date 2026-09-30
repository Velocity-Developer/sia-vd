<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\PengaturanAkademik;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use App\Models\User;
use App\PengingatTugasAkhir;

/**
 * Kelas Skripsi (tanpa dosen, kapasitas 1) di tahun akademik aktif yang periode KRS-nya berjalan, satu tahun akademik
 * lama yang sudah tidak aktif, dan mahasiswa aktif pada semester tertentu.
 *
 * @return array{0: User, 1: KelasKuliah, 2: TahunAkademik}
 */
function skripsiSetup(int $semester = 9): array
{
    $biasa = createMateriKelasKuliah();
    $aktif = $biasa->tahunAkademik;
    $aktif->update(['tanggal_krs_awal' => now()->subDay(), 'tanggal_krs_akhir' => now()->addDay()]);
    $lama = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Genap', 'tanggal_mulai' => '2025-02-01', 'tanggal_akhir' => '2025-07-31', 'tanggal_krs_awal' => '2025-02-01', 'tanggal_krs_akhir' => '2025-02-14', 'status' => false]);
    $suffix = bin2hex(random_bytes(3));
    $skripsi = MataKuliah::create(['kode_matkul' => "TA{$suffix}", 'nama_matkul' => 'Skripsi', 'sks' => 6, 'semester' => 8, 'jenis' => 'Wajib', 'tugas_akhir' => true, 'prodi_id' => $biasa->mataKuliah->prodi_id]);
    $kelasTa = KelasKuliah::create(['kode_kelas' => "TA{$suffix}-A", 'tahun_akademik_id' => $aktif->id, 'kapasitas' => 1, 'dosen_id' => null, 'matkul_id' => $skripsi->id]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    $mahasiswa->mahasiswaProfile->update(['prodi_id' => $skripsi->prodi_id, 'angkatan' => angkatanUntuk($kelasTa, $semester), 'status' => 'Aktif']);

    return [$mahasiswa->fresh(), $kelasTa, $lama];
}

/**
 * Satu mata kuliah biasa bernilai di tahun akademik tertentu (mis. untuk mencapai SKS lulus minimal).
 */
function nilaiMatkulBiasa(MahasiswaProfile $mahasiswa, TahunAkademik $tahun, int $sks, string $nilai = 'A'): Krs
{
    $suffix = bin2hex(random_bytes(3));
    $matkul = MataKuliah::create(['kode_matkul' => "MK{$suffix}", 'nama_matkul' => "Matkul {$suffix}", 'sks' => $sks, 'semester' => 1, 'jenis' => 'Wajib', 'prodi_id' => $mahasiswa->prodi_id]);
    $kelas = KelasKuliah::create(['kode_kelas' => "K{$suffix}", 'tahun_akademik_id' => $tahun->id, 'kapasitas' => 30, 'dosen_id' => User::factory()->dosen()->create()->dosenProfile->id, 'matkul_id' => $matkul->id]);

    return Krs::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif', 'nilai' => $nilai]);
}

/**
 * KRS Skripsi di tahun akademik lama yang belum dinilai (berlanjut).
 */
function skripsiBerlanjut(MahasiswaProfile $mahasiswa, KelasKuliah $kelasTa, TahunAkademik $lama): Krs
{
    $kelasLama = KelasKuliah::create(['kode_kelas' => $kelasTa->kode_kelas.'-L', 'tahun_akademik_id' => $lama->id, 'kapasitas' => 30, 'dosen_id' => null, 'matkul_id' => $kelasTa->matkul_id]);

    return Krs::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelasLama->id, 'status' => 'Aktif']);
}

it('offers Skripsi from its semester regardless of Ganjil/Genap, locked until the minimum passed credits', function () {
    [$mahasiswa, $kelasTa, $lama] = skripsiSetup(semester: 7);
    $profil = $mahasiswa->mahasiswaProfile;

    // Semester 7 belum sampai semester Skripsi (8).
    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where('kelasKuliahs', fn ($kelas) => ! collect($kelas)->contains('id', $kelasTa->id)));

    // Semester 9 (Ganjil) tetap ditawarkan walau Skripsi ada di semester Genap.
    $profil->update(['angkatan' => angkatanUntuk($kelasTa, 9)]);
    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page
            ->where('kelasKuliahs', fn ($kelas) => collect($kelas)->contains('id', $kelasTa->id))
            ->where("terkunciMatkul.{$kelasTa->matkul_id}", 'Minimal 120 SKS lulus di luar TA/Skripsi (Anda baru 0 SKS)'));
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelasTa))
        ->assertSessionHas('krs_error', fn (string $pesan): bool => str_contains($pesan, 'Minimal 120 SKS'));

    // Nilai E tidak dihitung lulus.
    nilaiMatkulBiasa($profil, $lama, 119);
    nilaiMatkulBiasa($profil, $lama, 3, 'E');
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelasTa))
        ->assertSessionHas('krs_error', fn (string $pesan): bool => str_contains($pesan, 'baru 119 SKS'));

    nilaiMatkulBiasa($profil, $lama, 1);
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelasTa))->assertSessionHas('krs_success');
    expect(Krs::where('kelas_id', $kelasTa->id)->where('mahasiswa_id', $profil->id)->exists())->toBeTrue();
});

it('lets an unfinished Skripsi be taken again next semester, without a capacity limit', function () {
    [$mahasiswa, $kelasTa, $lama] = skripsiSetup();
    $profil = $mahasiswa->mahasiswaProfile;
    nilaiMatkulBiasa($profil, $lama, 120);
    skripsiBerlanjut($profil, $kelasTa, $lama);
    // Kapasitas kelas (1) sudah terisi mahasiswa lain.
    Krs::create(['mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id, 'kelas_id' => $kelasTa->id, 'status' => 'Aktif']);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where("labelMatkul.{$kelasTa->matkul_id}", 'Lanjutan TA'));
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelasTa))->assertSessionHas('krs_success');

    // Skripsi yang sudah dinilai tidak ditawarkan lagi.
    Krs::where('kelas_id', $kelasTa->id)->where('mahasiswa_id', $profil->id)->delete();
    Krs::where('mahasiswa_id', $profil->id)->whereHas('kelasKuliah', fn ($q) => $q->where('matkul_id', $kelasTa->matkul_id))->update(['nilai' => 'A']);
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelasTa))
        ->assertSessionHas('krs_error', 'Anda sudah lulus mata kuliah ini dengan nilai A.');
});

it('leaves an unfinished Skripsi out of the previous IPS and shows it as Berlanjut on the KHS', function () {
    [$mahasiswa, $kelasTa, $lama] = skripsiSetup();
    $profil = $mahasiswa->mahasiswaProfile;
    nilaiMatkulBiasa($profil, $lama, 3, 'B');
    skripsiBerlanjut($profil, $kelasTa, $lama);

    expect($profil->ipsSemesterSebelum($kelasTa->tahunAkademik)['ips'])->toBe(3.0);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.hasil-studi', ['tahun_akademik_id' => $lama->id]))
        ->assertInertia(fn ($page) => $page
            ->where('krs', fn ($krs) => collect($krs)->where('berlanjut', true)->count() === 1)
            ->where('ringkasan.ip', 3));
});

it('fills the Skripsi grade only from the defence', function () {
    [$mahasiswa, $kelasTa] = skripsiSetup();
    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasTa->id, 'status' => 'Aktif']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.kelas-kuliah.show', $kelasTa))
        ->assertInertia(fn ($page) => $page->where('kelasTugasAkhir', true)->where('remidi', null));
    $this->actingAs($admin)->put(route('admin.kelas-kuliah.krs.nilai', [$kelasTa, $krs]), ['nilai' => 'A'])
        ->assertSessionHas('error', 'Nilai TA/Skripsi terisi otomatis dari hasil pendadaran dan tidak bisa diubah di sini.');
    $this->actingAs($admin)->post(route('admin.kelas-kuliah.finalisasi-nilai', $kelasTa))->assertForbidden();

    expect($krs->fresh()->nilai)->toBeNull()->and($kelasTa->fresh()->nilai_final_at)->toBeNull();
});

it('keeps schedules, meetings, and class content out of a Skripsi class', function () {
    [, $kelasTa] = skripsiSetup();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.kelas-kuliah.jadwal.store', $kelasTa), [])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.kelas-kuliah.materi.create', $kelasTa))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.presensi.generate', $kelasTa))->assertForbidden();
});

it('allows a Skripsi class without a lecturer but still requires one for other classes', function () {
    [, $kelasTa] = skripsiSetup();
    $admin = User::factory()->admin()->create();
    $biasa = MataKuliah::where('tugas_akhir', false)->first();
    $data = ['tahun_akademik_id' => $kelasTa->tahun_akademik_id, 'kapasitas' => 30, 'jumlah_pertemuan' => 16, 'dosen_id' => null];

    $this->actingAs($admin)->post(route('admin.kelas-kuliah.store'), [...$data, 'kode_kelas' => 'TA-B', 'matkul_id' => $kelasTa->matkul_id])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.kelas-kuliah.store'), [...$data, 'kode_kelas' => 'BIASA-B', 'matkul_id' => $biasa->id])->assertSessionHasErrors('dosen_id');

    expect(KelasKuliah::where('kode_kelas', 'TA-B')->value('dosen_id'))->toBeNull();
});

it('bills only the Skripsi credits once the student has only the Skripsi left', function () {
    [$mahasiswa, $kelasTa, $lama] = skripsiSetup();
    $profil = $mahasiswa->mahasiswaProfile;
    nilaiMatkulBiasa($profil, $lama, 3);
    $normal = PengaturanAkademik::maksSksUntuk($profil->ipsSemesterSebelum($kelasTa->tahunAkademik)['ips']);

    // Masih kurang SKS untuk pendadaran: kuota dari IPS seperti biasa.
    expect(TagihanSemester::kuotaSks($profil, $kelasTa->tahunAkademik))->toBe($normal);

    PengaturanAkademik::current()->update(['min_sks_pendadaran' => 3]);
    expect(TagihanSemester::kuotaSks($profil, $kelasTa->tahunAkademik))->toBe(6);

    // Setelah Skripsi dinilai, kembali ke kuota biasa.
    skripsiBerlanjut($profil, $kelasTa, $lama)->update(['nilai' => 'A']);
    expect(TagihanSemester::kuotaSks($profil, $kelasTa->tahunAkademik))->not->toBe(6);
});

it('reminds the student to take an unfinished Skripsi again', function () {
    [$mahasiswa, $kelasTa, $lama] = skripsiSetup();
    $profil = $mahasiswa->mahasiswaProfile;
    $teks = 'Tugas akhir Anda berlanjut: ambil lagi mata kuliah TA/Skripsi di KRS semester ini.';

    expect(PengingatTugasAkhir::untukMahasiswa($profil))->toBeNull();

    skripsiBerlanjut($profil, $kelasTa, $lama);
    expect(collect(PengingatTugasAkhir::untukMahasiswa($profil)['pesan'])->pluck('teks'))->toContain($teks);

    Krs::create(['mahasiswa_id' => $profil->id, 'kelas_id' => $kelasTa->id, 'status' => 'Aktif']);
    expect(PengingatTugasAkhir::untukMahasiswa($profil))->toBeNull();
});
