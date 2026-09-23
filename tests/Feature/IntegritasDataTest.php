<?php

use App\Models\Fakultas;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Materi;
use App\Models\PengaturanInstitusi;
use App\Models\PengumpulanTugas;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('explains why a dosen who is dekan, kaprodi, or dosen wali cannot be deleted', function (string $jabatan) {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();
    $dosen = User::factory()->dosen()->create();
    $profilId = $dosen->dosenProfile->id;

    match ($jabatan) {
        'dekan' => Fakultas::query()->whereKey($kelas->mataKuliah->prodi->fakultas_id)->update(['dekan_id' => $profilId]),
        'kaprodi' => ProgramStudi::query()->whereKey($kelas->mataKuliah->prodi_id)->update(['kaprodi' => $profilId]),
        'wali' => User::factory()->mahasiswa()->create()->mahasiswaProfile->update(['dosen_wali_id' => $profilId]),
    };

    $this->actingAs($admin)->delete(route('admin.users.dosen.destroy', $dosen))
        ->assertSessionHas('error', fn (string $pesan): bool => str_contains($pesan, $jabatan === 'wali' ? 'dosen wali' : $jabatan));

    expect($dosen->fresh())->not->toBeNull();
})->with(['dekan', 'kaprodi', 'wali']);

it('does not delete a program studi that still has students', function () {
    $admin = User::factory()->admin()->create();
    $acuan = createMateriKelasKuliah()->mataKuliah->prodi;
    $prodi = ProgramStudi::create(['fakultas_id' => $acuan->fakultas_id, 'kaprodi' => $acuan->kaprodi, 'kode_prodi' => 'KOSONG', 'nama_prodi' => 'Prodi Tanpa Matkul', 'jenjang' => 'S1', 'status_akreditasi' => 'Baik', 'tanggal_akreditasi_mulai' => '2022-01-01', 'tanggal_akreditasi_akhir' => '2027-01-01', 'tahun_berdiri' => 2020]);
    User::factory()->mahasiswa()->create()->mahasiswaProfile->update(['prodi_id' => $prodi->id]);

    $this->actingAs($admin)->delete(route('admin.program-studi.destroy', $prodi))->assertSessionHas('error');

    expect($prodi->fresh())->not->toBeNull();
});

it('does not delete a tahun akademik that has classes', function () {
    $kelas = createMateriKelasKuliah();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.tahun-akademik.destroy', $kelas->tahunAkademik))
        ->assertSessionHas('error');

    expect(TahunAkademik::find($kelas->tahun_akademik_id))->not->toBeNull();
});

it('gives a duplicated materi its own copy of every file', function () {
    Storage::fake('local');
    $kelas = createMateriKelasKuliah();
    $tujuan = KelasKuliah::create(['kode_kelas' => 'TUJUAN', 'tahun_akademik_id' => $kelas->tahun_akademik_id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id]);
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.materi.store', $kelas), [
        'judul_materi' => 'Modul', 'pertemuan_ke' => 1, 'jenis' => 'Materi', 'file' => [UploadedFile::fake()->create('modul.pdf', 5)],
    ]);
    $asli = Materi::firstOrFail();

    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.materi.duplicate', [$kelas, $asli]), ['target_ids' => [$tujuan->id]]);
    $salinan = Materi::where('kelas_id', $tujuan->id)->firstOrFail();

    expect($salinan->file[0])->not->toBe($asli->file[0]);
    Storage::disk('local')->assertExists($salinan->file[0]);

    $this->actingAs($dosen)->delete(route('dosen.kelas-kuliah.materi.destroy', [$kelas, $asli]));

    Storage::disk('local')->assertMissing($asli->file[0]);
    Storage::disk('local')->assertExists($salinan->file[0]);
});

it('does not let a student replace a graded submission', function () {
    Storage::fake('local');
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_tugas' => 'Tugas']);
    $pengumpulan = PengumpulanTugas::create(['tugas_id' => $tugas->id, 'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'file_jawaban' => ['pengumpulan-tugas/lama.pdf'], 'nilai' => 85, 'submitted_at' => now()]);

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.tugas.pengumpulan.store', $tugas), ['file_jawaban' => [UploadedFile::fake()->create('baru.pdf', 5)]])
        ->assertSessionHas('error');

    expect($pengumpulan->fresh()->file_jawaban)->toBe(['pengumpulan-tugas/lama.pdf']);
});

it('allows the same class code in another tahun akademik but not twice in one', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();
    $lain = TahunAkademik::create(['tahun' => '2026/2027', 'semester' => 'Ganjil', 'tanggal_mulai' => '2026-08-01', 'tanggal_akhir' => '2027-01-31', 'tanggal_krs_awal' => '2026-08-01', 'tanggal_krs_akhir' => '2026-08-14', 'status' => false]);
    $data = ['kode_kelas' => $kelas->kode_kelas, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id];

    $this->actingAs($admin)->post(route('admin.kelas-kuliah.store'), $data + ['tahun_akademik_id' => $kelas->tahun_akademik_id])
        ->assertSessionHasErrors('kode_kelas');
    $this->actingAs($admin)->post(route('admin.kelas-kuliah.store'), $data + ['tahun_akademik_id' => $lain->id])
        ->assertSessionHasNoErrors();

    expect(KelasKuliah::where('kode_kelas', $kelas->kode_kelas)->count())->toBe(2);
});

it('rejects a duplicate tahun + semester', function () {
    $kelas = createMateriKelasKuliah();
    $ada = $kelas->tahunAkademik;

    $this->actingAs(User::factory()->admin()->create())->post(route('admin.tahun-akademik.store'), [
        'tahun' => $ada->tahun, 'semester' => $ada->semester, 'tanggal_mulai' => '2025-08-01', 'tanggal_akhir' => '2026-01-31',
        'tanggal_krs_awal' => '2025-08-01', 'tanggal_krs_akhir' => '2025-08-14', 'status' => false,
    ])->assertSessionHasErrors('tahun');
});

it('only offers classes from the same tahun akademik as duplicate targets', function () {
    $kelas = createMateriKelasKuliah();
    $lain = TahunAkademik::create(['tahun' => '2026/2027', 'semester' => 'Ganjil', 'tanggal_mulai' => '2026-08-01', 'tanggal_akhir' => '2027-01-31', 'tanggal_krs_awal' => '2026-08-01', 'tanggal_krs_akhir' => '2026-08-14', 'status' => false]);
    $sama = KelasKuliah::create(['kode_kelas' => 'SAMA', 'tahun_akademik_id' => $kelas->tahun_akademik_id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id]);
    $beda = KelasKuliah::create(['kode_kelas' => 'BEDA', 'tahun_akademik_id' => $lain->id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id]);

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page
            ->where('otherClasses', fn ($kelasList) => collect($kelasList)->pluck('id')->all() === [$sama->id])
            ->where('otherClasses.0.nama_matkul', $kelas->mataKuliah->nama_matkul));
});

it('lists users without a query per row', function () {
    $admin = User::factory()->admin()->create();
    $hitung = function () use ($admin): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.users.mahasiswa'))->assertOk();

        return count(DB::getQueryLog());
    };

    User::factory()->mahasiswa()->count(2)->create();
    $hitung(); // request pertama ikut memuat cache pengaturan institusi
    $sedikit = $hitung();
    User::factory()->mahasiswa()->count(6)->create();

    expect($hitung())->toBe($sedikit);
});

it('refreshes the cached institution name after it is changed', function () {
    PengaturanInstitusi::current();
    expect(PengaturanInstitusi::shared()['nama_pt'])->toBe('SIA VD');

    PengaturanInstitusi::current()->update(['nama_pt' => 'Universitas Contoh']);

    expect(PengaturanInstitusi::shared()['nama_pt'])->toBe('Universitas Contoh');
});

it('does not let the course or academic year of a class with KRS be changed', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();
    $lain = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'A']);
    $payload = ['kode_kelas' => $kelas->kode_kelas, 'tahun_akademik_id' => $kelas->tahun_akademik_id, 'kapasitas' => 40, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id];
    $matkulAwal = $kelas->matkul_id;
    $tahunAwal = $kelas->tahun_akademik_id;

    $this->actingAs($admin)->put(route('admin.kelas-kuliah.update', $kelas), [...$payload, 'matkul_id' => $lain->matkul_id])
        ->assertSessionHasErrors('matkul_id');

    $tahunBaru = TahunAkademik::create(['tahun' => '2027/2028', 'semester' => 'Ganjil', 'tanggal_mulai' => '2027-08-01', 'tanggal_akhir' => '2028-01-31', 'tanggal_krs_awal' => '2027-08-01', 'tanggal_krs_akhir' => '2027-08-14', 'status' => false]);
    $this->actingAs($admin)->put(route('admin.kelas-kuliah.update', $kelas), [...$payload, 'tahun_akademik_id' => $tahunBaru->id])
        ->assertSessionHasErrors('tahun_akademik_id');

    // Kapasitas tetap boleh diubah.
    $this->actingAs($admin)->put(route('admin.kelas-kuliah.update', $kelas), $payload)->assertSessionHasNoErrors();

    $kelas->refresh();
    expect($kelas->matkul_id)->toBe($matkulAwal)
        ->and($kelas->tahun_akademik_id)->toBe($tahunAwal)
        ->and($kelas->kapasitas)->toBe(40);
});
