<?php

use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Pertemuan;
use App\Models\RiwayatPresensiDosen;
use App\Models\Ruang;
use App\Models\User;

/**
 * Kelas (TA mulai 1 Agustus 2025) berjadwal Senin 08:00–10:00 dengan satu mahasiswa, pertemuannya sudah dibuat.
 */
function kelasPresensiDosen(): KelasKuliah
{
    $kelas = createMateriKelasKuliah();
    $ruang = Ruang::firstOrCreate(['kode_ruang' => 'R-201'], ['nama_ruang' => 'Ruang 201', 'kapasitas' => 40]);
    Jadwal::create(['kelas_id' => $kelas->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $ruang->id]);
    Krs::create(['mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    Pertemuan::generateUntuk($kelas);

    return $kelas->fresh();
}

function pertemuanDosen(KelasKuliah $kelas, int $ke): Pertemuan
{
    return Pertemuan::where('kelas_id', $kelas->id)->where('pertemuan_ke', $ke)->firstOrFail();
}

/** Dosen pengampu membuka pertemuan pukul $masuk lalu menutupnya dengan jurnal. */
function dosenMengajar($test, KelasKuliah $kelas, int $ke, string $masuk, string $topik = 'Pengantar'): Pertemuan
{
    $pertemuan = pertemuanDosen($kelas, $ke);
    $test->travelTo($pertemuan->tanggal->toDateString().' '.$masuk);
    $dosen = $kelas->dosen->user;
    $test->actingAs($dosen)->post(route('dosen.presensi.pertemuan.mulai', $pertemuan))->assertSessionHasNoErrors();
    $test->actingAs($dosen)->post(route('dosen.presensi.pertemuan.selesai', $pertemuan), ['topik' => $topik])->assertSessionHasNoErrors();

    return $pertemuan->fresh();
}

it('records lecturer attendance from the meeting the lecturer opens and closes', function () {
    $kelas = kelasPresensiDosen();
    $pertemuan = dosenMengajar($this, $kelas, 1, '08:20:00');

    expect($pertemuan->status_dosen)->toBe('hadir')
        ->and($pertemuan->dosen_masuk_at->format('H:i'))->toBe('08:20')
        ->and($pertemuan->verifikasi)->toBeNull()
        ->and($pertemuan->menitTerlambat(15))->toBe(20);

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.presensi-dosen.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->component('Admin/PresensiDosen')
            ->where('pertemuan.data', fn ($data) => collect($data)->firstWhere('id', $pertemuan->id)['menit_terlambat'] === 20));
});

it('lets admin record a missed meeting as a make-up with check-in and check-out times', function () {
    $kelas = kelasPresensiDosen();
    $pertemuan = pertemuanDosen($kelas, 1);
    $this->travelTo('2025-08-06 09:00:00');
    $admin = User::factory()->admin()->create();
    $pengganti = User::factory()->dosen()->create()->dosenProfile;

    // Hadir harus dosen pengampu; dosen lain = Digantikan.
    $this->actingAs($admin)->put(route('admin.presensi-dosen.update', $pertemuan), [
        'status_dosen' => 'hadir', 'dosen_id' => $pengganti->id, 'jam_masuk' => '08:05', 'jam_keluar' => '09:55', 'topik' => 'Bab 1', 'alasan' => 'Lupa buka',
    ])->assertSessionHasErrors('dosen_id');

    $this->actingAs($admin)->put(route('admin.presensi-dosen.update', $pertemuan), [
        'status_dosen' => 'digantikan', 'dosen_id' => $pengganti->id, 'jam_masuk' => '08:05', 'jam_keluar' => '09:55', 'topik' => 'Bab 1', 'alasan' => 'Lupa buka',
    ])->assertSessionHas('success');

    $pertemuan->refresh();
    expect($pertemuan->status)->toBe(Pertemuan::SELESAI)
        ->and($pertemuan->status_dosen)->toBe('digantikan')
        ->and($pertemuan->dosen_id)->toBe($pengganti->id)
        ->and($pertemuan->dosen_masuk_at->format('Y-m-d H:i'))->toBe('2025-08-04 08:05')
        ->and($pertemuan->presensiMahasiswas()->count())->toBe(1)
        ->and($pertemuan->riwayatPresensiDosen()->first()->alasan)->toBe('Lupa buka');
});

it('allows absence statuses only for meetings that did not take place', function () {
    $kelas = kelasPresensiDosen();
    $admin = User::factory()->admin()->create();
    $terlaksana = dosenMengajar($this, $kelas, 1, '08:00:00');
    $this->travelTo('2025-08-12 12:00:00');
    $tidak = pertemuanDosen($kelas, 2);

    $this->actingAs($admin)->put(route('admin.presensi-dosen.update', $terlaksana), ['status_dosen' => 'sakit', 'alasan' => 'Surat dokter'])
        ->assertSessionHasErrors('status_dosen');

    $this->actingAs($admin)->put(route('admin.presensi-dosen.update', $tidak), ['status_dosen' => 'sakit', 'alasan' => 'Surat dokter'])
        ->assertSessionHas('success');
    expect($tidak->fresh())->status_dosen->toBe('sakit')->status->toBe(Pertemuan::DIJADWALKAN);

    $this->actingAs($admin)->get(route('admin.presensi-dosen.rekap', ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'semua' => 1]))
        ->assertInertia(fn ($page) => $page->component('Admin/RekapPresensiDosen')
            ->where('baris.0.terlaksana', 1)->where('baris.0.sakit', 1)->where('baris.0.jam', 2)->where('baris.0.sks', 3));

    // Bawaan rekap hanya menghitung yang terverifikasi.
    $this->actingAs($admin)->get(route('admin.presensi-dosen.rekap', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->where('baris.0.terlaksana', 0)->where('baris.0.belum_verifikasi', 1));
});

it('verifies, rejects, and unlocks lecturer attendance', function () {
    $kelas = kelasPresensiDosen();
    $p1 = dosenMengajar($this, $kelas, 1, '08:00:00');
    $p2 = dosenMengajar($this, $kelas, 2, '08:00:00');
    $admin = User::factory()->admin()->create();
    $dosen = $kelas->dosen->user;

    $this->actingAs($admin)->get(route('admin.verifikasi-presensi-dosen.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->component('Admin/VerifikasiPresensiDosen')->where('jumlahMenunggu', 2));

    $this->actingAs($admin)->post(route('admin.verifikasi-presensi-dosen.setujui'), ['ids' => [$p1->id]])->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.verifikasi-presensi-dosen.tolak'), ['ids' => [$p2->id], 'catatan' => 'Topik kurang lengkap'])->assertSessionHas('success');

    expect($p1->fresh()->verifikasi)->toBe(Pertemuan::DISETUJUI)->and($p1->fresh()->diverifikasi_oleh)->toBe($admin->id)
        ->and($p2->fresh()->verifikasi)->toBe(Pertemuan::DITOLAK);

    // Terverifikasi: dosen dan admin tidak bisa mengubah jurnal/koreksi.
    $this->actingAs($dosen)->put(route('dosen.presensi.pertemuan.jurnal', $p1), ['topik' => 'Ubah'])->assertForbidden();
    $this->actingAs($admin)->put(route('admin.presensi-dosen.update', $p1), [
        'status_dosen' => 'hadir', 'jam_masuk' => '08:00', 'jam_keluar' => '10:00', 'topik' => 'Ubah', 'alasan' => 'x',
    ])->assertSessionHas('error');

    // Ditolak: dosen memperbaiki jurnal → kembali menunggu.
    $this->actingAs($dosen)->put(route('dosen.presensi.pertemuan.jurnal', $p2), ['topik' => 'Pengantar lengkap'])->assertSessionHas('success');
    expect($p2->fresh()->verifikasi)->toBeNull();

    // Batal verifikasi membuka kunci.
    $this->actingAs($admin)->post(route('admin.verifikasi-presensi-dosen.batal'), ['ids' => [$p1->id]])->assertSessionHasErrors('alasan');
    $this->actingAs($admin)->post(route('admin.verifikasi-presensi-dosen.batal'), ['ids' => [$p1->id], 'alasan' => 'Salah setujui'])->assertSessionHas('success');
    $this->actingAs($dosen)->put(route('dosen.presensi.pertemuan.jurnal', $p1), ['topik' => 'Ubah'])->assertSessionHas('success');

    expect(RiwayatPresensiDosen::where('pertemuan_id', $p1->id)->pluck('aksi')->all())->toContain(RiwayatPresensiDosen::SETUJUI, RiwayatPresensiDosen::BATAL);
});

it('skips meetings without a journal when approving', function () {
    $kelas = kelasPresensiDosen();
    $p1 = dosenMengajar($this, $kelas, 1, '08:00:00');
    $p1->update(['topik' => null]);

    $this->actingAs(User::factory()->admin()->create())->post(route('admin.verifikasi-presensi-dosen.setujui'), ['ids' => [$p1->id]])
        ->assertSessionHas('success', fn ($pesan) => str_contains($pesan, '1 dilewati'));
    expect($p1->fresh()->verifikasi)->toBeNull();
});

it('prints BAP: draft for admin, only verified meetings for lecturers', function () {
    $kelas = kelasPresensiDosen();
    $p1 = dosenMengajar($this, $kelas, 1, '08:00:00');
    $admin = User::factory()->admin()->create();
    $dosen = $kelas->dosen->user;

    $this->actingAs($admin)->get(route('admin.presensi.pertemuan.bap', $p1))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($admin)->get(route('admin.presensi.bap', $kelas))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($dosen)->get(route('dosen.presensi.pertemuan.bap', $p1))->assertSessionHas('error');
    $this->actingAs($dosen)->get(route('dosen.presensi.bap', $kelas))->assertSessionHas('error');

    $this->actingAs($admin)->post(route('admin.verifikasi-presensi-dosen.setujui'), ['ids' => [$p1->id]]);

    $this->actingAs($dosen)->get(route('dosen.presensi.pertemuan.bap', $p1))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($dosen)->get(route('dosen.presensi.bap', $kelas))->assertOk();
    $this->actingAs(User::factory()->dosen()->create())->get(route('dosen.presensi.pertemuan.bap', $p1))->assertForbidden();
});

it('restricts the new menus to their permissions', function () {
    $dosen = User::factory()->dosen()->create();

    $this->actingAs($dosen)->get(route('admin.presensi-dosen.index'))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.verifikasi-presensi-dosen.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/admin/presensi/laporan-dosen')->assertRedirect('/admin/presensi-dosen/per-kelas');
});
