<?php

use App\Models\BatasSks;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengaturanAkademik;
use App\Models\SkalaNilai;
use App\Models\TahunAkademik;
use App\Models\User;

/**
 * Mahasiswa dengan satu mata kuliah bernilai $nilai di semester lalu, dan kelas tahun aktif yang periode KRS-nya berjalan.
 */
function mahasiswaDenganNilaiLalu(?string $nilai): array
{
    $kelas = createMateriKelasKuliah();
    $kelas->tahunAkademik->update(['tanggal_krs_awal' => now()->subDay(), 'tanggal_krs_akhir' => now()->addDay()]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    $mahasiswa->mahasiswaProfile->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'semester' => $kelas->mataKuliah->semester, 'status' => 'Aktif']);

    if ($nilai !== null) {
        $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Genap', 'tanggal_mulai' => '2025-02-01', 'tanggal_akhir' => '2025-07-31', 'tanggal_krs_awal' => '2025-02-01', 'tanggal_krs_akhir' => '2025-02-14', 'status' => false]);
        $kelasLalu = KelasKuliah::create(['kode_kelas' => 'LALU-X', 'tahun_akademik_id' => $lalu->id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => createMateriKelasKuliah()->matkul_id]);
        Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasLalu->id, 'nilai' => $nilai]);
    }

    return [$mahasiswa->fresh(), $kelas];
}

it('sets the SKS limit from the previous semester IPS', function (?string $nilai, int $maksSks) {
    [$mahasiswa] = mahasiswaDenganNilaiLalu($nilai);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where('maksSks', $maksSks));
})->with([
    'IPS 4.00' => ['A', 24],
    'IPS 2.00' => ['C', 18],
    'IPS 1.00' => ['D', 15],
    'belum ada IPS' => [null, 20],
]);

it('lets admin change the SKS tiers', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.batas-sks'), [
        'maks_sks_tanpa_ips' => 22,
        'batas_sks' => [['ips_minimal' => 3.5, 'maks_sks' => 24], ['ips_minimal' => 0, 'maks_sks' => 12]],
    ])->assertSessionHas('success');

    expect(PengaturanAkademik::current()->maks_sks_tanpa_ips)->toBe(22)
        ->and(BatasSks::count())->toBe(2)
        ->and(PengaturanAkademik::maksSksUntuk(3.2))->toBe(12)
        ->and(PengaturanAkademik::maksSksUntuk(3.6))->toBe(24);
});

it('requires an SKS tier starting at IPS 0', function () {
    $this->actingAs(User::factory()->admin()->create())->put(route('admin.pengaturan-akademik.batas-sks'), [
        'maks_sks_tanpa_ips' => 20,
        'batas_sks' => [['ips_minimal' => 2, 'maks_sks' => 24]],
    ])->assertSessionHasErrors('batas_sks');

    expect(BatasSks::count())->toBe(4);
});

it('lets admin add plus/minus grades that are then usable and weighted', function () {
    $admin = User::factory()->admin()->create();
    $skala = SkalaNilai::query()->get(['huruf', 'bobot', 'lulus', 'boleh_diulang'])->toArray();

    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.skala-nilai'), [
        'skala_nilai' => [...$skala, ['huruf' => 'a-', 'bobot' => 3.75, 'lulus' => true, 'boleh_diulang' => false]],
    ])->assertSessionHas('success');

    expect(SkalaNilai::where('huruf', 'A-')->value('bobot'))->toBe(3.75);

    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);

    $this->actingAs($kelas->dosen->user)
        ->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'A-'])
        ->assertSessionHas('success');

    $this->actingAs($mahasiswa)->get(route('mahasiswa.transkrip'))
        ->assertInertia(fn ($page) => $page->where('transkrip.0.bobot', 3.75)->where('ringkasan.ipk', 3.75));
});

it('does not remove a grade letter that students already have', function () {
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'E']);
    $tanpaE = SkalaNilai::query()->where('huruf', '!=', 'E')->get(['huruf', 'bobot', 'lulus', 'boleh_diulang'])->toArray();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pengaturan-akademik.skala-nilai'), ['skala_nilai' => $tanpaE])
        ->assertSessionHasErrors('skala_nilai');

    expect(SkalaNilai::where('huruf', 'E')->exists())->toBeTrue();
});

it('keeps academic settings admin-only', function () {
    $this->actingAs(User::factory()->dosen()->create())
        ->get(route('pengaturan-sistem.akademik'))
        ->assertForbidden();
});
