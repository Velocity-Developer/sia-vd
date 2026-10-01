<?php

use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\Ujian;
use App\Models\User;

/**
 * Mahasiswa aktif + kelas di tahun akademik aktif yang periode KRS-nya sedang berjalan.
 */
function verifikasiKrsKelas(): array
{
    $kelas = createMateriKelasKuliah();
    $kelas->tahunAkademik->update(['tanggal_krs_awal' => now()->subDay(), 'tanggal_krs_akhir' => now()->addDay()]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    $mahasiswa->mahasiswaProfile->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'angkatan' => angkatanUntuk($kelas), 'status' => 'Aktif']);

    return [$mahasiswa->fresh(), $kelas->fresh()];
}

/**
 * Verifikasi KRS menyala, periode KRS berjalan, dan mahasiswa sudah mengambil satu kelas.
 */
function verifikasiKrsSetup(): array
{
    PengaturanAkademik::current()->update(['verifikasi_krs_aktif' => true]);
    [$mahasiswa, $kelas] = verifikasiKrsKelas();
    test()->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))->assertSessionHas('krs_success');

    return [$mahasiswa, $kelas, User::factory()->admin()->create()];
}

function ajukanKrs(User $mahasiswa)
{
    return test()->actingAs($mahasiswa)->post(route('mahasiswa.krs.simpan'), ['konfirmasi' => true]);
}

it('menyimpan KRS langsung final saat verifikasi mati', function () {
    [$mahasiswa, $kelas] = verifikasiKrsKelas();
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas));

    ajukanKrs($mahasiswa)->assertSessionHas('krs_success', 'KRS berhasil disimpan dan dikunci.');

    expect(KrsSemester::query()->sole()->status)->toBe(KrsSemester::DISETUJUI);
});

it('mengajukan KRS dan menguncinya selama menunggu verifikasi', function () {
    [$mahasiswa, $kelas] = verifikasiKrsSetup();

    ajukanKrs($mahasiswa)->assertSessionHas('krs_success', 'KRS berhasil diajukan dan menunggu verifikasi admin.');
    expect(KrsSemester::query()->sole()->status)->toBe(KrsSemester::DIAJUKAN);

    $this->actingAs($mahasiswa)->delete(route('mahasiswa.krs.destroy', Krs::query()->sole()))
        ->assertSessionHas('krs_error', fn (string $pesan): bool => str_contains($pesan, 'menunggu verifikasi'));

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where('statusKrs.status', KrsSemester::DIAJUKAN)->where('krsTersimpan', true)->where('verifikasiKrs', true));
});

it('memungkinkan admin menyetujui KRS yang diajukan', function () {
    [$mahasiswa, , $admin] = verifikasiKrsSetup();
    ajukanKrs($mahasiswa);
    $kunci = KrsSemester::query()->sole();

    $this->actingAs($admin)->get(route('admin.verifikasi-krs.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/VerifikasiKrs')->has('krs.data', 1)->where('jumlah.diajukan', 1));
    $this->actingAs($admin)->get(route('admin.verifikasi-krs.show', $kunci))
        ->assertInertia(fn ($page) => $page->component('Admin/VerifikasiKrsShow')->has('kelas', 1));

    $this->actingAs($admin)->post(route('admin.verifikasi-krs.setujui', $kunci))->assertSessionHas('success');

    $kunci->refresh();
    expect($kunci->status)->toBe(KrsSemester::DISETUJUI)
        ->and($kunci->diverifikasi_oleh)->toBe($admin->id);
});

it('mengembalikan KRS untuk revisi lalu mahasiswa mengajukan ulang', function () {
    [$mahasiswa, $kelas, $admin] = verifikasiKrsSetup();
    ajukanKrs($mahasiswa);
    $kunci = KrsSemester::query()->sole();

    $this->actingAs($admin)->post(route('admin.verifikasi-krs.revisi', $kunci), [])->assertSessionHasErrors('catatan');
    $this->actingAs($admin)->post(route('admin.verifikasi-krs.revisi', $kunci), ['catatan' => 'Ganti kelas'])->assertSessionHas('success');

    expect($kunci->refresh()->status)->toBe(KrsSemester::PERLU_REVISI);
    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where('statusKrs.catatan_revisi', 'Ganti kelas')->where('statusKrs.bisa_direvisi', true)->where('krsTersimpan', false));

    $this->actingAs($mahasiswa)->delete(route('mahasiswa.krs.destroy', Krs::query()->sole()))->assertSessionHas('krs_success');
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))->assertSessionHas('krs_success');
    ajukanKrs($mahasiswa)->assertSessionHas('krs_success');

    expect($kunci->refresh()->status)->toBe(KrsSemester::DIAJUKAN);
});

it('membuka revisi sampai akhir masa revisi walau periode KRS sudah lewat', function () {
    [$mahasiswa, $kelas, $admin] = verifikasiKrsSetup();
    ajukanKrs($mahasiswa);
    $kelas->tahunAkademik->update([
        'tanggal_krs_awal' => now()->subDays(10),
        'tanggal_krs_akhir' => now()->subDay(),
        'tanggal_revisi_krs_akhir' => now()->addDays(3),
    ]);

    $this->actingAs($admin)->post(route('admin.verifikasi-krs.revisi', KrsSemester::query()->sole()), ['catatan' => 'SKS kurang'])
        ->assertSessionHas('success');

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where('periodeKrsAktif', true)->has('kelasKuliahs', 1));
    $this->actingAs($mahasiswa)->delete(route('mahasiswa.krs.destroy', Krs::query()->sole()))->assertSessionHas('krs_success');
});

it('tidak membuka masa revisi bagi mahasiswa yang belum pernah menyimpan KRS', function () {
    [$mahasiswa, $kelas] = verifikasiKrsSetup();
    $kelas->tahunAkademik->update([
        'tanggal_krs_awal' => now()->subDays(10),
        'tanggal_krs_akhir' => now()->subDay(),
        'tanggal_revisi_krs_akhir' => now()->addDays(3),
    ]);

    ajukanKrs($mahasiswa)->assertSessionHas('krs_error', 'Periode pengambilan KRS belum dibuka atau sudah berakhir.');
    expect(KrsSemester::count())->toBe(0);
});

it('mengunci semua KRS setelah masa revisi habis, tetapi yang diajukan masih bisa disetujui', function () {
    [$mahasiswa, $kelas, $admin] = verifikasiKrsSetup();
    ajukanKrs($mahasiswa);
    $kunci = KrsSemester::query()->sole();
    $this->actingAs($admin)->post(route('admin.verifikasi-krs.revisi', $kunci), ['catatan' => 'Perbaiki']);

    $kelas->tahunAkademik->update([
        'tanggal_krs_awal' => now()->subDays(10),
        'tanggal_krs_akhir' => now()->subDays(5),
        'tanggal_revisi_krs_akhir' => now()->subDay(),
    ]);

    $this->actingAs($mahasiswa)->delete(route('mahasiswa.krs.destroy', Krs::query()->sole()))
        ->assertSessionHas('krs_error', fn (string $pesan): bool => str_contains($pesan, 'Masa revisi KRS sudah berakhir'));

    // Tanpa tanggal khusus, admin tidak bisa mengembalikan KRS setelah masa revisi berakhir.
    $this->actingAs($admin)->post(route('admin.verifikasi-krs.revisi', $kunci), ['catatan' => 'Lagi'])
        ->assertSessionHas('error', fn (string $pesan): bool => str_contains($pesan, 'Dibuka sampai'));

    $this->actingAs($admin)->post(route('admin.verifikasi-krs.setujui', $kunci))->assertSessionHas('success');
    expect($kunci->refresh()->status)->toBe(KrsSemester::DISETUJUI);
});

it('memungkinkan admin membuka kunci mahasiswa tertentu dengan batas tanggal sendiri', function () {
    [$mahasiswa, $kelas, $admin] = verifikasiKrsSetup();
    ajukanKrs($mahasiswa);
    $this->actingAs($admin)->post(route('admin.verifikasi-krs.setujui', KrsSemester::query()->sole()));
    $kelas->tahunAkademik->update([
        'tanggal_krs_awal' => now()->subDays(10),
        'tanggal_krs_akhir' => now()->subDays(5),
        'tanggal_revisi_krs_akhir' => now()->subDay(),
    ]);

    $this->actingAs($admin)->delete(route('admin.users.mahasiswa.buka-kunci-krs', $mahasiswa))
        ->assertSessionHas('error', fn (string $pesan): bool => str_contains($pesan, 'Dibuka sampai'));
    $this->actingAs($admin)->delete(route('admin.users.mahasiswa.buka-kunci-krs', $mahasiswa), ['dibuka_sampai' => now()->subDay()->toDateString()])
        ->assertSessionHasErrors('dibuka_sampai');
    $this->actingAs($admin)->delete(route('admin.users.mahasiswa.buka-kunci-krs', $mahasiswa), ['dibuka_sampai' => now()->addDays(2)->toDateString()])
        ->assertSessionHas('success');

    $this->actingAs($mahasiswa)->delete(route('mahasiswa.krs.destroy', Krs::query()->sole()))->assertSessionHas('krs_success');
});

it('menyetujui KRS secara massal hanya yang berstatus diajukan', function () {
    [$mahasiswa, $kelas, $admin] = verifikasiKrsSetup();
    ajukanKrs($mahasiswa);
    $lain = User::factory()->mahasiswa()->create();
    $lain->mahasiswaProfile->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'angkatan' => angkatanUntuk($kelas), 'status' => 'Aktif']);
    $kosong = KrsSemester::create(['mahasiswa_id' => $lain->mahasiswaProfile->id, 'tahun_akademik_id' => $kelas->tahun_akademik_id, 'status' => KrsSemester::DIAJUKAN]);

    $this->actingAs($admin)->post(route('admin.verifikasi-krs.setujui-massal'), ['ids' => KrsSemester::pluck('id')->all()])
        ->assertSessionHas('success', '1 KRS disetujui.');

    expect(KrsSemester::query()->where('mahasiswa_id', $mahasiswa->mahasiswaProfile->id)->value('status'))->toBe(KrsSemester::DISETUJUI)
        ->and($kosong->refresh()->status)->toBe(KrsSemester::DIAJUKAN);
});

it('menahan kartu ujian sampai KRS disetujui', function () {
    [$mahasiswa, $kelas, $admin] = verifikasiKrsSetup();
    ajukanKrs($mahasiswa);
    Ujian::create([
        'kelas_id' => $kelas->id,
        'jenis' => Pertemuan::UTS,
        'tanggal' => now()->addWeek()->toDateString(),
        'jam_mulai' => '08:00',
        'jam_akhir' => '10:00',
        'mode' => Ujian::TATAP_MUKA,
        'status' => 'terbit',
    ]);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.ujian.kartu', ['jenis' => Pertemuan::UTS, 'tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertRedirect()
        ->assertSessionHas('error', fn (string $pesan): bool => str_contains($pesan, 'belum disetujui'));

    $this->actingAs($admin)->post(route('admin.verifikasi-krs.setujui', KrsSemester::query()->sole()));

    $this->actingAs($mahasiswa)->get(route('mahasiswa.ujian.kartu', ['jenis' => Pertemuan::UTS, 'tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertOk();
});

it('menampilkan KRS menunggu verifikasi di dashboard admin', function () {
    [$mahasiswa, , $admin] = verifikasiKrsSetup();
    ajukanKrs($mahasiswa);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('tindakan', fn ($tindakan): bool => collect($tindakan)->contains(fn ($b): bool => $b['judul'] === 'KRS menunggu verifikasi' && $b['jumlah'] === 1)));
});

it('menolak halaman verifikasi KRS tanpa izin', function () {
    [$mahasiswa] = verifikasiKrsSetup();

    $this->actingAs($mahasiswa)->get(route('admin.verifikasi-krs.index'))->assertForbidden();
});
