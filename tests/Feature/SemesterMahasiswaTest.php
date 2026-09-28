<?php

use App\Models\MahasiswaProfile;
use App\Models\TahunAkademik;
use App\Models\User;
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
