<?php

use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\PengaturanAkademik;
use App\Models\User;

/**
 * Mahasiswa aktif + kelas di tahun akademik aktif. Periode KRS sengaja sudah lewat: menu admin tidak terikat periode.
 */
function menuKrsSetup(): array
{
    $kelas = createMateriKelasKuliah();
    $kelas->tahunAkademik->update(['tanggal_krs_awal' => now()->subDays(10), 'tanggal_krs_akhir' => now()->subDays(5)]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    $mahasiswa->mahasiswaProfile->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'angkatan' => angkatanUntuk($kelas), 'status' => 'Aktif', 'nim' => '2301A001']);

    return [$mahasiswa->fresh()->mahasiswaProfile, $kelas->fresh(), User::factory()->admin()->create()];
}

it('mengisi KRS mahasiswa lewat Input KRS di luar periode KRS lalu menyetujuinya', function () {
    [$mhs, $kelas, $admin] = menuKrsSetup();

    $this->actingAs($admin)->get(route('admin.input-krs.index', ['mahasiswa_id' => $mhs->id]))
        ->assertInertia(fn ($page) => $page->component('Admin/InputKrs')
            ->has('krs.ditawarkan', 1)->where('krs.ditawarkan.0.kelas_id', $kelas->id)->has('krs.diambil', 0));

    $this->actingAs($admin)->post(route('admin.input-krs.store', ['mahasiswa' => $mhs, 'kelasKuliah' => $kelas]))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.input-krs.store', ['mahasiswa' => $mhs, 'kelasKuliah' => $kelas]))->assertSessionHas('error');
    expect(Krs::query()->where('mahasiswa_id', $mhs->id)->count())->toBe(1);

    $this->actingAs($admin)->post(route('admin.input-krs.simpan', $mhs), ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'setujui' => true])
        ->assertSessionHas('success', 'KRS disimpan dan disetujui.');

    $kunci = KrsSemester::query()->sole();
    expect($kunci->status)->toBe(KrsSemester::DISETUJUI)->and($kunci->diverifikasi_oleh)->toBe($admin->id);
});

it('mengeluarkan kelas dari KRS lewat Input KRS kecuali yang sudah bernilai', function () {
    [$mhs, $kelas, $admin] = menuKrsSetup();
    $krs = Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

    $krs->update(['nilai' => 'A']);
    $this->actingAs($admin)->delete(route('admin.input-krs.destroy', $krs))->assertSessionHas('error');

    $krs->update(['nilai' => null]);
    $this->actingAs($admin)->delete(route('admin.input-krs.destroy', $krs))->assertSessionHas('success');
    expect(Krs::query()->count())->toBe(0);
});

it('menyimpan KRS input admin sebagai diajukan saat verifikasi aktif tanpa disetujui', function () {
    PengaturanAkademik::current()->update(['verifikasi_krs_aktif' => true]);
    [$mhs, $kelas, $admin] = menuKrsSetup();

    $this->actingAs($admin)->post(route('admin.input-krs.simpan', $mhs), ['tahun_akademik_id' => $kelas->tahun_akademik_id])
        ->assertSessionHas('error');

    Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    $this->actingAs($admin)->post(route('admin.input-krs.simpan', $mhs), ['tahun_akademik_id' => $kelas->tahun_akademik_id])
        ->assertSessionHas('success', 'KRS disimpan dan menunggu verifikasi.');

    expect(KrsSemester::query()->sole()->status)->toBe(KrsSemester::DIAJUKAN);
});

it('mengubah status KRS menjadi Ya dan Tidak', function () {
    [$mhs, $kelas, $admin] = menuKrsSetup();
    Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    $ta = ['tahun_akademik_id' => $kelas->tahun_akademik_id];

    $this->actingAs($admin)->get(route('admin.status-krs.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/StatusKrs')->has('mahasiswa.data', 1)->where('mahasiswa.data.0.final', false));

    $this->actingAs($admin)->post(route('admin.status-krs.ya', ['mahasiswa' => $mhs, ...$ta]))->assertSessionHas('success');
    expect(KrsSemester::query()->sole()->status)->toBe(KrsSemester::DISETUJUI);
    $this->actingAs($admin)->get(route('admin.status-krs.index', ['status' => 'ya']))->assertInertia(fn ($page) => $page->has('mahasiswa.data', 1));
    $this->actingAs($admin)->get(route('admin.status-krs.index', ['status' => 'tidak']))->assertInertia(fn ($page) => $page->has('mahasiswa.data', 0));

    // Masa KRS & revisi sudah lewat: membuka KRS butuh batas tanggal.
    $this->actingAs($admin)->post(route('admin.status-krs.tidak', ['mahasiswa' => $mhs, ...$ta]))->assertSessionHas('error');
    $this->actingAs($admin)->post(route('admin.status-krs.tidak', ['mahasiswa' => $mhs, ...$ta]), ['dibuka_sampai' => now()->addDays(2)->toDateString()])
        ->assertSessionHas('success');

    $kunci = KrsSemester::query()->sole();
    expect($kunci->status)->toBe(KrsSemester::PERLU_REVISI)->and($kunci->bisaDirevisi())->toBeTrue();
});

it('menolak status Ya untuk KRS tanpa kelas', function () {
    [$mhs, $kelas, $admin] = menuKrsSetup();
    KrsSemester::kembalikan($mhs->id, $kelas->tahun_akademik_id, null, now()->addDay()->toDateString(), $admin->id);

    $this->actingAs($admin)->post(route('admin.status-krs.ya', ['mahasiswa' => $mhs, 'tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertSessionHas('error');
    expect(KrsSemester::query()->sole()->status)->toBe(KrsSemester::PERLU_REVISI);
});

it('mencetak KST dan kartu ujian hanya untuk KRS yang disetujui', function () {
    [$mhs, $kelas, $admin] = menuKrsSetup();
    Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    $ta = ['tahun_akademik_id' => $kelas->tahun_akademik_id];

    $this->actingAs($admin)->get(route('admin.cetak-kst.index'))->assertInertia(fn ($page) => $page->component('Admin/CetakKrs')->has('mahasiswa.data', 0));
    $this->actingAs($admin)->get(route('admin.cetak-kst.cetak', ['mahasiswa' => $mhs, ...$ta]))->assertNotFound();

    KrsSemester::simpan($mhs->id, $kelas->tahun_akademik_id)->setujui($admin->id);

    $this->actingAs($admin)->get(route('admin.cetak-kst.index'))
        ->assertInertia(fn ($page) => $page->where('mode', 'kst')->has('mahasiswa.data', 1)->where('mahasiswa.data.0.sks', $kelas->mataKuliah->sks));
    $this->actingAs($admin)->get(route('admin.cetak-kst.cetak', ['mahasiswa' => $mhs, ...$ta]))
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs($admin)->get(route('admin.kartu-ujian.index', ['jenis' => 'uas']))
        ->assertInertia(fn ($page) => $page->where('mode', 'kartu')->where('filter.jenis', 'uas')->has('mahasiswa.data', 1));
    $this->actingAs($admin)->get(route('admin.kartu-ujian.cetak', ['mahasiswa' => $mhs, 'jenis' => 'uas', ...$ta]))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('merekap KRS per kelas dan per mahasiswa beserta unduhan CSV', function () {
    [$mhs, $kelas, $admin] = menuKrsSetup();
    Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    KrsSemester::simpan($mhs->id, $kelas->tahun_akademik_id);

    $this->actingAs($admin)->get(route('admin.rekap-krs.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/RekapKrs')
            ->where('baris.0.id', $kelas->id)->where('baris.0.peserta', 1)->where('baris.0.disetujui', 1)
            ->where('ringkasan.mahasiswa', 1)->where('ringkasan.disetujui', 1));

    $this->actingAs($admin)->get(route('admin.rekap-krs.index', ['tampilan' => 'mahasiswa']))
        ->assertInertia(fn ($page) => $page->has('baris', 1)->where('baris.0.nim', '2301A001')->where('baris.0.status', KrsSemester::DISETUJUI));

    $csv = $this->actingAs($admin)->get(route('admin.rekap-krs.index', ['tampilan' => 'mahasiswa', 'format' => 'csv']));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('2301A001')->toContain('Disetujui');
});

it('menutup menu KRS admin bagi pengguna tanpa izin', function () {
    $dosen = User::factory()->dosen()->create();

    foreach (['admin.input-krs.index', 'admin.status-krs.index', 'admin.cetak-kst.index', 'admin.kartu-ujian.index', 'admin.rekap-krs.index'] as $rute) {
        $this->actingAs($dosen)->get(route($rute))->assertForbidden();
    }
});

it('membolehkan mahasiswa mencetak KST sendiri hanya setelah KRS disetujui', function () {
    [$mhs, $kelas, $admin] = menuKrsSetup();
    Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    $user = $mhs->user;

    $this->actingAs($user)->get(route('mahasiswa.krs.kst'))
        ->assertRedirect(route('mahasiswa.krs'))->assertSessionHas('krs_error');

    KrsSemester::simpan($mhs->id, $kelas->tahun_akademik_id)->setujui($admin->id);

    $this->actingAs($user)->get(route('mahasiswa.krs.kst'))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('menghitung batas SKS Input KRS dari IPS semester sebelumnya seperti halaman mahasiswa', function () {
    [$mhs, , $admin] = menuKrsSetup();
    $user = $mhs->user;

    $mahasiswaPage = $this->actingAs($user)->get(route('mahasiswa.krs'))->viewData('page')['props'];
    $this->actingAs($admin)->get(route('admin.input-krs.index', ['mahasiswa_id' => $mhs->id]))
        ->assertInertia(fn ($page) => $page->where('krs.maks_sks', $mahasiswaPage['maksSks']));
});

it('menampilkan menu Cetak KST dan Kartu Ujian mahasiswa beserta alasannya', function () {
    [$mhs, $kelas, $admin] = menuKrsSetup();
    Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    $user = $mhs->user;

    $this->actingAs($user)->get(route('mahasiswa.cetak-kst'))
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/CetakKst')
            ->where('tahunAkademikId', $kelas->tahun_akademik_id)
            ->where('alasan', 'KRS belum disetujui pada tahun akademik ini.'));

    KrsSemester::simpan($mhs->id, $kelas->tahun_akademik_id)->setujui($admin->id);

    $this->actingAs($user)->get(route('mahasiswa.cetak-kst'))->assertInertia(fn ($page) => $page->where('alasan', null));
    $this->actingAs($user)->get(route('mahasiswa.cetak-kartu-ujian'))
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/CetakKartuUjian')
            ->has('kartu', 2)->where('kartu.0.jenis', 'uts')->where('kartu.0.alasan', null)->where('kartu.1.jenis', 'uas'));
});
