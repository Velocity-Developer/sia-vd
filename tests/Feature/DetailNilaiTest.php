<?php

use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\RemidiPeserta;
use App\Models\User;

/**
 * Satu mahasiswa dengan dua kelas di tahun akademik aktif; komponen tunggal 100% (lihat aturKomponenNilai()).
 *
 * @return array{0: MahasiswaProfile, 1: list<Krs>, 2: User}
 */
function detailNilaiSetup(): array
{
    $kelasA = createMateriKelasKuliah();
    $kelasB = createMateriKelasKuliah($kelasA->tahunAkademik);
    $mhs = User::factory()->mahasiswa()->create()->mahasiswaProfile;
    $mhs->update(['prodi_id' => $kelasA->mataKuliah->prodi_id, 'nim' => '2301A001', 'angkatan' => 2023]);
    $krs = [
        Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelasA->id, 'status' => 'Aktif']),
        Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelasB->id, 'status' => 'Aktif']),
    ];
    aturKomponenNilai();

    return [$mhs->fresh(), $krs, User::factory()->admin()->create()];
}

it('lists students with KRS and shows their grades per course', function () {
    [$mhs, $krs, $admin] = detailNilaiSetup();
    $tahunId = $krs[0]->kelasKuliah->tahun_akademik_id;
    isiNilaiKomponen($this, $krs[0]->kelasKuliah, $krs[0], 85, $admin)->assertSessionHas('success');

    $this->actingAs($admin)->get(route('admin.detail-nilai.index', ['tahun_akademik_id' => $tahunId]))
        ->assertInertia(fn ($page) => $page->component('Admin/DetailNilai')->where('mahasiswa.total', 1)
            ->where('mahasiswa.data.0.nim', '2301A001')->where('mahasiswa.data.0.angkatan', 2023));

    $this->actingAs($admin)->get(route('admin.detail-nilai.show', ['mahasiswa' => $mhs, 'tahun_akademik_id' => $tahunId]))
        ->assertInertia(fn ($page) => $page->component('Admin/DetailNilaiMahasiswa')->has('nilai', 2)->has('komponen', 1)
            ->where('nilai', fn ($nilai) => collect($nilai)->firstWhere('krs_id', $krs[0]->id)['huruf'] === 'A'
                && collect($nilai)->firstWhere('krs_id', $krs[0]->id)['nilai_angka'] == 85
                && collect($nilai)->firstWhere('krs_id', $krs[1]->id)['huruf'] === null));

    $this->actingAs($admin)->get(route('admin.validasi-nilai.index', ['tahun_akademik_id' => $tahunId]))
        ->assertInertia(fn ($page) => $page->component('Admin/ValidasiNilai')->where('mahasiswa.total', 1));

    // Menu Validasi Nilai hanya nilai akhir & huruf (tanpa angka komponen).
    $this->actingAs($admin)->get(route('admin.validasi-nilai.show', ['mahasiswa' => $mhs, 'tahun_akademik_id' => $tahunId]))
        ->assertInertia(fn ($page) => $page->component('Admin/ValidasiNilaiMahasiswa')->has('nilai', 2)->missing('komponen')
            ->missing('nilai.0.nilai')
            ->where('nilai', fn ($nilai) => collect($nilai)->firstWhere('krs_id', $krs[0]->id)['huruf'] === 'A'
                && collect($nilai)->firstWhere('krs_id', $krs[0]->id)['nilai_angka'] == 85));
});

it('validates graded courses only and locks them until the validation is cancelled', function () {
    [$mhs, $krs, $admin] = detailNilaiSetup();
    $tahunId = $krs[0]->kelasKuliah->tahun_akademik_id;
    $kelas = $krs[0]->kelasKuliah;

    $this->actingAs($admin)->post(route('admin.validasi-nilai.validasi', $mhs), ['tahun_akademik_id' => $tahunId])->assertSessionHas('error');

    isiNilaiKomponen($this, $kelas, $krs[0], 75, $admin);
    // Nilai kelas berdosen baru bisa divalidasi setelah dosen mengirimnya ke validasi.
    $this->actingAs($admin)->get(route('admin.validasi-nilai.show', ['mahasiswa' => $mhs, 'tahun_akademik_id' => $tahunId]))
        ->assertInertia(fn ($page) => $page->where('nilai', fn ($nilai) => collect($nilai)->firstWhere('krs_id', $krs[0]->id)['menunggu_dosen'] === true));
    $this->actingAs($admin)->post(route('admin.validasi-nilai.validasi', $mhs), ['tahun_akademik_id' => $tahunId])
        ->assertSessionHas('error', 'Nilai belum bisa divalidasi: dosen pengampu belum mengirim nilai kelasnya ke validasi.');
    $this->actingAs($kelas->dosen->user)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('success');

    $this->flushSession()->actingAs($admin)->post(route('admin.validasi-nilai.validasi', $mhs), ['tahun_akademik_id' => $tahunId])
        ->assertSessionHas('success', '1 nilai mata kuliah divalidasi. 1 mata kuliah belum bernilai sehingga belum divalidasi.');
    expect($krs[0]->fresh())->nilai_divalidasi_oleh->toBe($admin->id)->nilai_divalidasi_at->not->toBeNull()
        ->and($krs[1]->fresh()->nilai_divalidasi_at)->toBeNull();

    // Terkunci untuk admin (tabel kelas & Nilai Semester).
    isiNilaiKomponen($this, $kelas, $krs[0], 95, $admin)->assertSessionHas('success');
    expect($krs[0]->fresh()->nilai)->toBe('B');
    $this->actingAs($admin)->get(route('admin.nilai-semester.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('mahasiswa.0.tervalidasi', true));

    // Remidi membuka validasi: huruf perbaikan tersimpan dan nilainya kembali menunggu validasi.
    RemidiPeserta::create(['kelas_id' => $kelas->id, 'mahasiswa_id' => $mhs->id, 'nilai_awal' => 'B', 'diusulkan' => true]);
    $kelas->update(['remidi_dikunci_at' => now()]);
    $this->actingAs($admin)->put(route('admin.kelas-kuliah.krs.nilai', [$kelas, $krs[0]]), ['nilai' => 'A'])
        ->assertSessionHas('success', fn (string $pesan) => str_contains($pesan, 'menunggu validasi'));
    expect($krs[0]->fresh())->nilai->toBe('A')->nilai_divalidasi_at->toBeNull();

    $this->actingAs($admin)->post(route('admin.validasi-nilai.validasi', $mhs), ['tahun_akademik_id' => $tahunId])->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.validasi-nilai.batal', $mhs), ['tahun_akademik_id' => $tahunId])->assertSessionHas('success');
    expect($krs[0]->fresh()->nilai_divalidasi_at)->toBeNull();
});

it('validates or cancels a single course from the Validasi Nilai menu', function () {
    [$mhs, $krs, $admin] = detailNilaiSetup();
    $tahunId = $krs[0]->kelasKuliah->tahun_akademik_id;
    isiNilaiKomponen($this, $krs[0]->kelasKuliah, $krs[0], 80, $admin);
    isiNilaiKomponen($this, $krs[1]->kelasKuliah, $krs[1], 70, $admin);
    $krs[0]->kelasKuliah->finalisasiNilai($admin);
    $krs[1]->kelasKuliah->finalisasiNilai($admin);

    $this->actingAs($admin)->post(route('admin.validasi-nilai.validasi', $mhs), ['tahun_akademik_id' => $tahunId, 'krs_id' => $krs[1]->id])
        ->assertSessionHas('success', '1 nilai mata kuliah divalidasi.');
    expect($krs[1]->fresh()->nilai_divalidasi_at)->not->toBeNull()->and($krs[0]->fresh()->nilai_divalidasi_at)->toBeNull();

    $this->actingAs($admin)->post(route('admin.validasi-nilai.validasi', $mhs), ['tahun_akademik_id' => $tahunId])
        ->assertSessionHas('success', '1 nilai mata kuliah divalidasi.');
    $this->actingAs($admin)->post(route('admin.validasi-nilai.batal', $mhs), ['tahun_akademik_id' => $tahunId, 'krs_id' => $krs[0]->id])
        ->assertSessionHas('success');
    expect($krs[0]->fresh()->nilai_divalidasi_at)->toBeNull()->and($krs[1]->fresh()->nilai_divalidasi_at)->not->toBeNull();

    // KRS mahasiswa lain tidak bisa dipakai lewat mahasiswa ini.
    $lain = Krs::create(['mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id, 'kelas_id' => $krs[0]->kelas_id, 'status' => 'Aktif']);
    $this->actingAs($admin)->post(route('admin.validasi-nilai.validasi', $mhs), ['tahun_akademik_id' => $tahunId, 'krs_id' => $lain->id])->assertNotFound();
});

it('keeps Detail Nilai and Validasi Nilai for permitted admins', function () {
    [$mhs, $krs] = detailNilaiSetup();
    $dosen = $krs[0]->kelasKuliah->dosen->user;

    $this->actingAs($dosen)->get(route('admin.detail-nilai.index'))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.detail-nilai.show', $mhs))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.validasi-nilai.index'))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.validasi-nilai.show', $mhs))->assertForbidden();
    $this->actingAs($dosen)->post(route('admin.validasi-nilai.validasi', $mhs), ['tahun_akademik_id' => $krs[0]->kelasKuliah->tahun_akademik_id])->assertForbidden();
});
