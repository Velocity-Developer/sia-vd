<?php

use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\TahunAkademik;
use App\Models\User;
use App\PengajuanCuti;
use Illuminate\Support\Facades\Schema;

function tahunUntukSemester(string $tahun, string $semester): TahunAkademik
{
    return new TahunAkademik(['tahun' => $tahun, 'semester' => $semester]);
}

it('counts the student semester from angkatan and the academic year, including leave', function () {
    $profil = new MahasiswaProfile(['angkatan' => 2024]);

    expect($profil->semesterPada(tahunUntukSemester('2024/2025', 'Ganjil')))->toBe(1)
        ->and($profil->semesterPada(tahunUntukSemester('2024/2025', 'Genap')))->toBe(2)
        ->and($profil->semesterPada(tahunUntukSemester('2026/2027', 'Ganjil')))->toBe(5)
        ->and($profil->semesterPada(tahunUntukSemester('2027/2028', 'Genap')))->toBe(8)
        // Belum mulai kuliah, atau tahun akademik tidak ada.
        ->and($profil->semesterPada(tahunUntukSemester('2023/2024', 'Genap')))->toBeNull()
        ->and($profil->semesterPada(null))->toBeNull();
});

it('no longer stores a semester column for students', function () {
    expect(Schema::hasColumn('mahasiswa_profiles', 'semester'))->toBeFalse();
});

it('shows the counted semester on the student detail page', function () {
    TahunAkademik::create(['tahun' => '2026/2027', 'semester' => 'Ganjil', 'tanggal_mulai' => '2026-09-01', 'tanggal_akhir' => '2027-01-31', 'tanggal_krs_awal' => '2026-09-01', 'tanggal_krs_akhir' => '2026-09-14', 'status' => true]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    $mahasiswa->mahasiswaProfile->update(['angkatan' => 2025]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.users.mahasiswa.show', $mahasiswa))
        ->assertInertia(fn ($page) => $page->where('user.semester', 3));
});

it('requires the academic year as YYYY/YYYY+1 with Ganjil or Genap', function (array $ubah, string $error) {
    $payload = ['tahun' => '2025/2026', 'semester' => 'Ganjil', 'tanggal_mulai' => '2025-08-01', 'tanggal_akhir' => '2026-01-31', 'tanggal_krs_awal' => '2025-08-01', 'tanggal_krs_akhir' => '2025-08-14', 'status' => false];

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tahun-akademik.store'), array_replace($payload, $ubah))
        ->assertSessionHasErrors($error);

    expect(TahunAkademik::count())->toBe(0);
})->with([
    'tahun satu angka' => [['tahun' => '2025'], 'tahun'],
    'tahun kedua tidak berurutan' => [['tahun' => '2025/2027'], 'tahun'],
    'semester pendek' => [['semester' => 'Pendek'], 'semester'],
]);

it('keeps the KRS period and grade deadline in order with the semester dates', function (array $ubah, string $error) {
    $payload = ['tahun' => '2025/2026', 'semester' => 'Ganjil', 'tanggal_mulai' => '2025-09-01', 'tanggal_akhir' => '2026-01-31', 'tanggal_krs_awal' => '2025-08-25', 'tanggal_krs_akhir' => '2025-09-14', 'batas_input_nilai' => '2026-02-07', 'status' => false];
    $admin = User::factory()->admin()->create();

    // KRS boleh dibuka sebelum tanggal mulai kuliah.
    $this->actingAs($admin)->post(route('admin.tahun-akademik.store'), $payload)->assertSessionHasNoErrors();
    TahunAkademik::query()->delete();

    $this->actingAs($admin)->post(route('admin.tahun-akademik.store'), array_replace($payload, $ubah))->assertSessionHasErrors($error);
    expect(TahunAkademik::count())->toBe(0);
})->with([
    'KRS ditutup setelah semester berakhir' => [['tanggal_krs_akhir' => '2026-02-01'], 'tanggal_krs_akhir'],
    'batas input nilai sebelum semester berakhir' => [['batas_input_nilai' => '2026-01-20'], 'batas_input_nilai'],
    'batas bayar remidi sebelum semester berakhir' => [['batas_input_nilai' => null, 'batas_bayar_remidi' => '2026-01-20'], 'batas_bayar_remidi'],
]);

it('menghitung semester dari semester masuk pada tahun akademik saat diisi', function () {
    $masuk = TahunAkademik::create(['tahun' => '2026/2027', 'semester' => 'Ganjil', 'tanggal_mulai' => '2026-09-01', 'tanggal_akhir' => '2027-01-31', 'tanggal_krs_awal' => '2026-09-01', 'tanggal_krs_akhir' => '2026-09-14', 'status' => true]);
    $profil = new MahasiswaProfile(['angkatan' => 2026, 'semester_masuk' => 3, 'tahun_akademik_masuk_id' => $masuk->id]);

    expect($profil->semesterPada($masuk))->toBe(3)
        ->and($profil->semesterPada(tahunUntukSemester('2026/2027', 'Genap')))->toBe(4)
        ->and($profil->semesterPada(tahunUntukSemester('2028/2029', 'Ganjil')))->toBe(7)
        ->and($profil->semesterPada(tahunUntukSemester('2025/2026', 'Ganjil')))->toBe(1)
        ->and($profil->semesterPada(tahunUntukSemester('2024/2025', 'Genap')))->toBeNull()
        ->and(MahasiswaProfile::galatSemesterMasuk(3, $masuk))->toBeNull()
        ->and(MahasiswaProfile::galatSemesterMasuk(4, $masuk))->toContain('Turunkan satu semester menjadi semester 3')
        ->and(MahasiswaProfile::galatSemesterMasuk(1, tahunUntukSemester('2026/2027', 'Genap')))->toContain('Semester 1 hanya bisa dimulai di semester Ganjil');
});

it('admin mengisi semester masuk mahasiswa pindahan sesuai paritas semester aktif', function () {
    // Helper kelas kuliah ikut membuat tahun akademik aktif; nonaktifkan agar hanya satu yang aktif.
    $prodiId = createMateriKelasKuliah()->mataKuliah->prodi->id;
    TahunAkademik::query()->update(['status' => false]);
    $aktif = TahunAkademik::create(['tahun' => '2026/2027', 'semester' => 'Genap', 'tanggal_mulai' => '2027-02-01', 'tanggal_akhir' => '2027-07-31', 'tanggal_krs_awal' => '2027-02-01', 'tanggal_krs_akhir' => '2027-02-14', 'status' => true]);
    $admin = User::factory()->admin()->create();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $profil = $mahasiswa->mahasiswaProfile;
    $profil->update(['angkatan' => 2026, 'status' => 'Pindahan', 'prodi_id' => $prodiId]);
    $kirim = fn (array $ubah) => $this->actingAs($admin)->put(route('admin.users.mahasiswa.update', $mahasiswa), [
        ...$mahasiswa->only(['name', 'username', 'email', 'role_id']),
        ...$profil->only(['tempat_lahir', 'jenis_kelamin', 'agama', 'no_telepon', 'alamat', 'kewarganegaraan', 'angkatan', 'status', 'prodi_id', 'nama_ibu_kandung']),
        'tanggal_lahir' => '2006-01-01',
        ...$ubah,
    ]);

    $kirim(['semester_masuk' => 3])->assertSessionHasErrors(['semester_masuk' => 'Semester 3 tidak sesuai semester Genap yang aktif (2026/2027). Turunkan satu semester menjadi semester 2 agar mahasiswa bisa kuliah di semester aktif.']);
    $kirim(['semester_masuk' => 4])->assertSessionHasNoErrors();
    expect($profil->fresh())->semester_masuk->toBe(4)->tahun_akademik_masuk_id->toBe($aktif->id)
        ->and($profil->fresh()->semesterPada($aktif))->toBe(4);

    // Simpan ulang tanpa mengubah semester masuk di tahun akademik lain: tetap memakai tahun akademik saat diisi.
    $aktif->update(['status' => false]);
    TahunAkademik::create(['tahun' => '2027/2028', 'semester' => 'Ganjil', 'tanggal_mulai' => '2027-08-01', 'tanggal_akhir' => '2028-01-31', 'tanggal_krs_awal' => '2027-08-01', 'tanggal_krs_akhir' => '2027-08-14', 'status' => true]);
    $kirim(['semester_masuk' => 4])->assertSessionHasNoErrors();
    expect($profil->fresh())->tahun_akademik_masuk_id->toBe($aktif->id)
        ->and($profil->fresh()->semesterPada(TahunAkademik::aktif()))->toBe(5);

    $kirim(['semester_masuk' => null])->assertSessionHasNoErrors();
    expect($profil->fresh())->semester_masuk->toBeNull()->tahun_akademik_masuk_id->toBeNull();
});

it('memperlakukan mahasiswa Pindahan sama dengan Aktif', function () {
    $pindahan = User::factory()->mahasiswa()->create();
    $pindahan->mahasiswaProfile->update(['status' => 'Pindahan']);
    $cuti = User::factory()->mahasiswa()->create();
    $cuti->mahasiswaProfile->update(['status' => 'Cuti']);

    expect(Krs::STATUS_MAHASISWA_BOLEH_KRS)->toContain('Pindahan')
        ->and($pindahan->mahasiswaProfile->fresh()->isAktif())->toBeTrue()
        ->and(MahasiswaProfile::query()->aktif()->pluck('id'))->toContain($pindahan->mahasiswaProfile->id)->not->toContain($cuti->mahasiswaProfile->id)
        ->and(PengajuanCuti::alasanTidakBolehCuti($pindahan->mahasiswaProfile->fresh()) ?? '')->not->toContain('Hanya mahasiswa berstatus');
});
