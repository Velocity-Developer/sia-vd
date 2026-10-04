<?php

use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\User;

/**
 * Satu mahasiswa ber-KRS bernilai di satu kelas.
 *
 * @return array{0: MahasiswaProfile, 1: Krs, 2: User}
 */
function hasilStudiAdminSetup(): array
{
    $kelas = createMateriKelasKuliah();
    $mhs = User::factory()->mahasiswa()->create()->mahasiswaProfile;
    $mhs->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'nim' => '2301H001', 'angkatan' => 2023]);
    $krs = Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif', 'nilai' => 'A', 'nilai_divalidasi_at' => now()]);

    return [$mhs->fresh(), $krs, User::factory()->admin()->create()];
}

it('lists students and shows the KHS of a chosen academic year', function () {
    [$mhs, $krs, $admin] = hasilStudiAdminSetup();
    $tahunId = $krs->kelasKuliah->tahun_akademik_id;

    $this->actingAs($admin)->get(route('admin.khs.index', ['tahun_akademik_id' => $tahunId, 'search' => '2301H']))
        ->assertInertia(fn ($page) => $page->component('Admin/Khs')->where('mahasiswa.total', 1)->where('mahasiswa.data.0.nim', '2301H001'));

    $this->actingAs($admin)->get(route('admin.khs.show', ['mahasiswa' => $mhs, 'tahun_akademik_id' => $tahunId]))
        ->assertInertia(fn ($page) => $page->component('Admin/KhsMahasiswa')->where('mahasiswa.nim', '2301H001')
            ->where('tahunAkademikTerpilih', $tahunId)->has('krs', 1)->where('krs.0.nilai', 'A'));
});

it('lists students and shows the transcript', function () {
    [$mhs, , $admin] = hasilStudiAdminSetup();

    $this->actingAs($admin)->get(route('admin.transkrip-nilai.index', ['search' => '2301H']))
        ->assertInertia(fn ($page) => $page->component('Admin/TranskripNilai')->where('mahasiswa.total', 1));

    $this->actingAs($admin)->get(route('admin.transkrip-nilai.show', $mhs))
        ->assertInertia(fn ($page) => $page->component('Admin/TranskripNilaiMahasiswa')->has('transkrip', 1)
            ->where('transkrip.0.nilai', 'A')->where('ringkasan.totalMatkul', 1));
});

it('downloads the KHS and transcript PDFs', function () {
    [$mhs, $krs, $admin] = hasilStudiAdminSetup();

    $this->actingAs($admin)->get(route('admin.khs.download', ['mahasiswa' => $mhs, 'tahun_akademik_id' => $krs->kelasKuliah->tahun_akademik_id]))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($admin)->get(route('admin.transkrip-nilai.download', $mhs))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('forbids users without the permission', function () {
    [$mhs] = hasilStudiAdminSetup();
    $dosen = User::factory()->dosen()->create();

    $this->actingAs($dosen)->get(route('admin.khs.index'))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.khs.download', $mhs))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.transkrip-nilai.show', $mhs))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.transkrip-nilai.download', $mhs))->assertForbidden();
});

it('keeps unvalidated grades out of the KHS and transcript until they are validated', function () {
    [$mhs, $krs] = hasilStudiAdminSetup();
    $krs->update(['nilai_divalidasi_at' => null]);
    $tahunId = $krs->kelasKuliah->tahun_akademik_id;

    $this->actingAs($mhs->user)->get(route('mahasiswa.hasil-studi', ['tahun_akademik_id' => $tahunId]))
        ->assertInertia(fn ($page) => $page->where('krs.0.nilai', null)->where('krs.0.menunggu_validasi', true)->where('ringkasan.ip', null));
    $this->actingAs($mhs->user)->get(route('mahasiswa.transkrip'))->assertInertia(fn ($page) => $page->has('transkrip', 0));

    $krs->update(['nilai_divalidasi_at' => now()]);
    $this->actingAs($mhs->user)->get(route('mahasiswa.hasil-studi', ['tahun_akademik_id' => $tahunId]))
        ->assertInertia(fn ($page) => $page->where('krs.0.nilai', 'A')->where('krs.0.menunggu_validasi', false)->where('ringkasan.ip', 4));
    $this->actingAs($mhs->user)->get(route('mahasiswa.transkrip'))->assertInertia(fn ($page) => $page->has('transkrip', 1));
});
