<?php

use App\Models\Fakultas;
use App\Models\KelasKuliah;
use App\Models\KomponenNilai;
use App\Models\Krs;
use App\Models\MataKuliah;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createMateriKelasKuliah(?TahunAkademik $tahunAkademik = null): KelasKuliah
{
    $suffix = bin2hex(random_bytes(3));
    $dosen = User::factory()->dosen()->create();
    $fakultas = Fakultas::create(['kode_fakultas' => "FT{$suffix}", 'nama_fakultas' => "Fakultas Teknologi Informasi {$suffix}", 'dekan_id' => $dosen->dosenProfile->id, 'tanggal_berdiri' => '2001-08-17', 'no_telp' => '021-5551001', 'email' => "fti-{$suffix}@example.ac.id"]);
    $prodi = ProgramStudi::create(['fakultas_id' => $fakultas->id, 'kode_prodi' => "TI-{$suffix}", 'nama_prodi' => 'Teknik Informatika', 'jenjang' => 'S1', 'status_akreditasi' => 'Unggul', 'tanggal_akreditasi_mulai' => '2022-06-01', 'tanggal_akreditasi_akhir' => '2027-06-01', 'kaprodi' => $dosen->dosenProfile->id, 'tahun_berdiri' => 2001]);
    $matkul = MataKuliah::create(['kode_matkul' => "IF{$suffix}", 'nama_matkul' => 'Algoritma dan Pemrograman', 'sks' => 3, 'semester' => 1, 'jenis' => 'Wajib', 'prodi_id' => $prodi->id]);
    $tahunAkademik ??= TahunAkademik::firstOrCreate(['tahun' => '2025/2026', 'semester' => 'Ganjil'], ['tanggal_mulai' => '2025-08-01', 'tanggal_akhir' => '2026-01-31', 'tanggal_krs_awal' => '2025-08-01', 'tanggal_krs_akhir' => '2025-08-14', 'status' => true]);

    return KelasKuliah::create(['kode_kelas' => "IF{$suffix}-A", 'tahun_akademik_id' => $tahunAkademik->id, 'kapasitas' => 30, 'dosen_id' => $dosen->dosenProfile->id, 'matkul_id' => $matkul->id]);
}

/**
 * Angkatan yang membuat mahasiswa berada di semester tertentu pada kelas itu
 * (semester dihitung dari angkatan, lihat MahasiswaProfile::semesterPada).
 */
function angkatanUntuk(KelasKuliah $kelas, ?int $semester = null): int
{
    $tahun = $kelas->tahunAkademik;
    $semester ??= $kelas->mataKuliah->semester;

    return $tahun->tahunAwal() - intdiv($semester - ($tahun->semester === 'Genap' ? 2 : 1), 2);
}

/**
 * Satu komponen nilai 100% agar kelas bisa dinilai; huruf dihitung dari angka (skala umum A ≥ 80, B ≥ 70, C ≥ 60,
 * D ≥ 50, E ≥ 0).
 */
function aturKomponenNilai(): KomponenNilai
{
    return KomponenNilai::create(['nama' => 'Nilai Akhir', 'persen' => 100, 'sumber' => KomponenNilai::MANUAL, 'urutan' => 0]);
}

/**
 * Isi angka satu KRS lewat tabel nilai per komponen di halaman kelas (dosen pengampu, atau admin bila $oleh diisi).
 */
function isiNilaiKomponen($test, KelasKuliah $kelas, Krs $krs, ?float $angka, ?User $oleh = null)
{
    $komponen = KomponenNilai::query()->first() ?? aturKomponenNilai();

    return $test->actingAs($oleh ?? $kelas->dosen->user)
        ->put(route(($oleh ? 'admin' : 'dosen').'.kelas-kuliah.nilai-komponen', $kelas), ['nilai' => [$krs->id => [$komponen->id => $angka]]]);
}
