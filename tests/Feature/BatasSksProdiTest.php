<?php

use App\Models\BatasSksProdi;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengaturanAkademik;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use App\Models\User;

/**
 * Mahasiswa prodi kelas aktif dengan satu nilai $nilai di semester lalu; periode KRS kelas aktif sedang berjalan.
 *
 * @return array{0: User, 1: ProgramStudi}
 */
function mahasiswaBerIps(?string $nilai): array
{
    $kelas = createMateriKelasKuliah();
    $kelas->tahunAkademik->update(['tanggal_krs_awal' => now()->subDay(), 'tanggal_krs_akhir' => now()->addDay()]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    $mahasiswa->mahasiswaProfile->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'angkatan' => angkatanUntuk($kelas), 'status' => 'Aktif']);

    if ($nilai !== null) {
        $lalu = TahunAkademik::firstOrCreate(['tahun' => '2024/2025', 'semester' => 'Genap'], ['tanggal_mulai' => '2025-02-01', 'tanggal_akhir' => '2025-07-31', 'tanggal_krs_awal' => '2025-02-01', 'tanggal_krs_akhir' => '2025-02-14', 'status' => false]);
        $kelasLalu = KelasKuliah::create(['kode_kelas' => 'LALU-'.bin2hex(random_bytes(2)), 'tahun_akademik_id' => $lalu->id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id]);
        Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasLalu->id, 'nilai' => $nilai]);
    }

    return [$mahasiswa->fresh(), $kelas->mataKuliah->prodi];
}

function aturBatasProdi(ProgramStudi $prodi): void
{
    test()->actingAs(User::factory()->admin()->create())->put(route('admin.batas-sks.update', $prodi), [
        'maks_sks_tanpa_ips' => 18,
        'batas_sks' => [['ips_minimal' => 3.5, 'maks_sks' => 22], ['ips_minimal' => 0, 'maks_sks' => 12]],
    ])->assertSessionHas('success');
}

it('prefills an unset prodi from the global SKS limits', function () {
    [, $prodi] = mahasiswaBerIps(null);

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.batas-sks.index', ['prodi' => $prodi->id]))
        ->assertInertia(fn ($page) => $page->component('Admin/BatasSks')->where('belumDiatur', true)
            ->where('maksSksTanpaIps', PengaturanAkademik::current()->maks_sks_tanpa_ips)->has('batasSks', 4));
});

it('uses the prodi SKS limits in KRS, and the global ones again after deleting them', function (?string $nilai, int $prodi, int $global) {
    [$mahasiswa, $programStudi] = mahasiswaBerIps($nilai);
    aturBatasProdi($programStudi);

    expect($programStudi->fresh()->maks_sks_tanpa_ips)->toBe(18)->and(BatasSksProdi::where('prodi_id', $programStudi->id)->count())->toBe(2);
    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))->assertInertia(fn ($page) => $page->where('maksSks', $prodi));

    $this->actingAs(User::factory()->admin()->create())->delete(route('admin.batas-sks.destroy', $programStudi))->assertSessionHas('success');
    expect($programStudi->fresh()->maks_sks_tanpa_ips)->toBeNull();
    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))->assertInertia(fn ($page) => $page->where('maksSks', $global));
})->with([
    'IPS 4.00' => ['A', 22, 24],
    'IPS 2.00' => ['C', 12, 18],
    'belum ada IPS' => [null, 18, 20],
]);

it('leaves other prodi on the global limits', function () {
    [, $prodiDiatur] = mahasiswaBerIps(null);
    [$lain] = mahasiswaBerIps('A');
    aturBatasProdi($prodiDiatur);

    expect(PengaturanAkademik::maksSksUntuk(4.0, $prodiDiatur->id))->toBe(22)
        ->and(PengaturanAkademik::maksSksUntuk(4.0, $lain->mahasiswaProfile->prodi_id))->toBe(24)
        ->and(PengaturanAkademik::maksSksUntuk(4.0))->toBe(24);
});

it('applies the same rules as the global SKS limits', function () {
    [, $prodi] = mahasiswaBerIps(null);

    $this->actingAs(User::factory()->admin()->create())->put(route('admin.batas-sks.update', $prodi), [
        'maks_sks_tanpa_ips' => 20,
        'batas_sks' => [['ips_minimal' => 2, 'maks_sks' => 24]],
    ])->assertSessionHasErrors(['batas_sks' => 'Harus ada baris dengan IPS minimal 0 agar semua IPS mendapat batas SKS.']);

    expect(BatasSksProdi::count())->toBe(0);
});

it('keeps the SKS limit menu admin-only', function () {
    [$mahasiswa, $prodi] = mahasiswaBerIps(null);

    $this->actingAs($mahasiswa)->get(route('admin.batas-sks.index'))->assertForbidden();
    $this->actingAs(User::factory()->dosen()->create())->put(route('admin.batas-sks.update', $prodi), [])->assertForbidden();
});
