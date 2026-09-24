<?php

use App\Models\DispensasiUjian;
use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengajuanIzin;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\Ruang;
use App\Models\User;
use App\SyaratUjian;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Kelas (TA mulai Jumat 1 Agustus 2025) dengan jadwal Senin 08:00–10:00 dan sejumlah mahasiswa ber-KRS.
 *
 * @return array{0: KelasKuliah, 1: list<User>}
 */
function kelasPresensi(int $jumlahMahasiswa = 3): array
{
    $kelas = createMateriKelasKuliah();
    $ruang = Ruang::firstOrCreate(['kode_ruang' => 'R-101'], ['nama_ruang' => 'Ruang 101', 'kapasitas' => 40]);
    Jadwal::create(['kelas_id' => $kelas->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $ruang->id]);

    $mahasiswa = collect($jumlahMahasiswa > 0 ? range(1, $jumlahMahasiswa) : [])->map(function () use ($kelas): User {
        $user = User::factory()->mahasiswa()->create();
        Krs::create(['mahasiswa_id' => $user->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

        return $user;
    })->all();

    return [$kelas->fresh(), $mahasiswa];
}

function pertemuanKe(KelasKuliah $kelas, int $ke): Pertemuan
{
    return Pertemuan::where('kelas_id', $kelas->id)->where('pertemuan_ke', $ke)->firstOrFail();
}

it('gives new classes the default number of meetings from the academic settings', function () {
    PengaturanAkademik::current()->update(['jumlah_pertemuan' => 14]);

    expect(createMateriKelasKuliah()->fresh()->jumlah_pertemuan)->toBe(14);
});

it('generates meetings from the weekly schedule without touching existing ones', function () {
    [$kelas] = kelasPresensi();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.presensi.generate', $kelas))->assertSessionHas('success');

    $pertemuan = Pertemuan::where('kelas_id', $kelas->id)->orderBy('pertemuan_ke')->get();
    expect($pertemuan)->toHaveCount(16)
        ->and($pertemuan->first()->tanggal->toDateString())->toBe('2025-08-04')
        ->and($pertemuan[1]->tanggal->toDateString())->toBe('2025-08-11')
        ->and($pertemuan->firstWhere('pertemuan_ke', 8)->jenis)->toBe(Pertemuan::UTS)
        ->and($pertemuan->firstWhere('pertemuan_ke', 16)->jenis)->toBe(Pertemuan::UAS)
        ->and($pertemuan->where('jenis', Pertemuan::KULIAH))->toHaveCount(14);

    // Pertemuan yang dijadwal ulang tidak ditimpa, yang terhapus dibuat kembali.
    pertemuanKe($kelas, 2)->update(['tanggal' => '2025-08-13']);
    pertemuanKe($kelas, 3)->delete();
    $this->actingAs($admin)->post(route('admin.presensi.generate', $kelas));

    expect(Pertemuan::where('kelas_id', $kelas->id)->count())->toBe(16)
        ->and(pertemuanKe($kelas, 2)->tanggal->toDateString())->toBe('2025-08-13')
        ->and(pertemuanKe($kelas, 3)->tanggal->toDateString())->toBe('2025-08-18');
});

it('refuses to generate meetings for a class without a schedule', function () {
    $kelas = createMateriKelasKuliah();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.presensi.generate', $kelas))
        ->assertSessionHasErrors('pertemuan');
});

it('lets admin change the number of meetings per class but not below a meeting already held', function () {
    [$kelas] = kelasPresensi();
    $admin = User::factory()->admin()->create();
    Pertemuan::generateUntuk($kelas);

    $this->actingAs($admin)->put(route('admin.presensi.jumlah', $kelas), ['jumlah_pertemuan' => 12])->assertSessionHasNoErrors();
    expect($kelas->fresh()->jumlah_pertemuan)->toBe(12)
        ->and(Pertemuan::where('kelas_id', $kelas->id)->max('pertemuan_ke'))->toBe(12);

    pertemuanKe($kelas, 10)->update(['status' => Pertemuan::SELESAI]);

    $this->actingAs($admin)->put(route('admin.presensi.jumlah', $kelas), ['jumlah_pertemuan' => 8])->assertSessionHasErrors('jumlah_pertemuan');
    expect($kelas->fresh()->jumlah_pertemuan)->toBe(12);

    // Lewat form kelas juga memakai aturan yang sama.
    $this->actingAs($admin)->put(route('admin.kelas-kuliah.update', $kelas), [
        ...$kelas->only(['kode_kelas', 'tahun_akademik_id', 'kapasitas', 'dosen_id', 'matkul_id']),
        'jumlah_pertemuan' => 9,
    ])->assertSessionHasErrors('jumlah_pertemuan');
});

it('lets the lecturer start a meeting only around its scheduled time', function () {
    [$kelas] = kelasPresensi();
    Pertemuan::generateUntuk($kelas);
    $pertemuan = pertemuanKe($kelas, 1);
    $dosen = $kelas->dosen->user;

    $this->travelTo('2025-08-04 07:30:00');
    $this->actingAs($dosen)->post(route('dosen.presensi.pertemuan.mulai', $pertemuan))->assertSessionHas('error');
    expect($pertemuan->fresh()->status)->toBe(Pertemuan::DIJADWALKAN);

    $this->travelTo('2025-08-04 07:50:00');
    $this->actingAs($dosen)->post(route('dosen.presensi.pertemuan.mulai', $pertemuan))->assertSessionHas('success');

    $pertemuan->refresh();
    expect($pertemuan->status)->toBe(Pertemuan::BERLANGSUNG)
        ->and($pertemuan->dosen_masuk_at->format('H:i'))->toBe('07:50')
        ->and($pertemuan->presensiMahasiswas()->count())->toBe(3)
        ->and($pertemuan->presensiMahasiswas()->where('status', PresensiMahasiswa::ALPA)->count())->toBe(3);
});

it('lets admin open a meeting outside its schedule without recording lecturer check-in', function () {
    [$kelas] = kelasPresensi();
    Pertemuan::generateUntuk($kelas);
    $pertemuan = pertemuanKe($kelas, 1);

    $this->travelTo('2025-08-20 13:00:00');
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.presensi.pertemuan.mulai', $pertemuan))->assertSessionHas('success');

    expect($pertemuan->fresh()->status)->toBe(Pertemuan::BERLANGSUNG)
        ->and($pertemuan->fresh()->dosen_masuk_at)->toBeNull();
});

it('blocks lecturers from other classes and students from the presensi pages', function () {
    [$kelas, $mahasiswa] = kelasPresensi();
    Pertemuan::generateUntuk($kelas);
    $lain = createMateriKelasKuliah();

    $this->actingAs($lain->dosen->user)->get(route('dosen.presensi.kelas', $kelas))->assertForbidden();
    $this->actingAs($lain->dosen->user)->get(route('dosen.presensi.pertemuan.show', pertemuanKe($kelas, 1)))->assertForbidden();
    $this->actingAs($mahasiswa[0])->get(route('dosen.presensi.index'))->assertForbidden();
    $this->actingAs($kelas->dosen->user)->put(route('admin.presensi.jumlah', $kelas), ['jumlah_pertemuan' => 10])->assertForbidden();
});

it('records attendance, requires a journal to finish, and computes the recap', function () {
    [$kelas, $mahasiswa] = kelasPresensi();
    Pertemuan::generateUntuk($kelas);
    $dosen = $kelas->dosen->user;
    [$a, $b, $c] = array_map(fn (User $user) => $user->mahasiswaProfile->id, $mahasiswa);

    // Pertemuan 1: A hadir, B terlambat, C izin. Pertemuan 2: A hadir, B sakit, C alpa.
    foreach ([1 => ['2025-08-04', [$a => 'hadir', $b => 'terlambat', $c => 'izin']], 2 => ['2025-08-11', [$a => 'hadir', $b => 'sakit']]] as $ke => [$tanggal, $status]) {
        $pertemuan = pertemuanKe($kelas, $ke);
        $this->travelTo("{$tanggal} 08:05:00");
        $this->actingAs($dosen)->post(route('dosen.presensi.pertemuan.mulai', $pertemuan));

        $this->actingAs($dosen)->put(route('dosen.presensi.pertemuan.mahasiswa', $pertemuan), [
            'presensi' => collect($status)->map(fn ($s, $id) => ['mahasiswa_id' => $id, 'status' => $s, 'keterangan' => $s === 'izin' ? 'Acara keluarga' : null])->values()->all(),
        ])->assertSessionHas('success');

        $this->actingAs($dosen)->post(route('dosen.presensi.pertemuan.selesai', $pertemuan), ['topik' => ''])->assertSessionHasErrors('topik');
        $this->actingAs($dosen)->post(route('dosen.presensi.pertemuan.selesai', $pertemuan), ['topik' => "Materi pertemuan {$ke}"])->assertSessionHas('success');
    }

    // UTS yang sudah selesai dan pertemuan batal tidak ikut dihitung.
    pertemuanKe($kelas, 8)->update(['status' => Pertemuan::SELESAI]);
    pertemuanKe($kelas, 8)->siapkanPeserta();
    pertemuanKe($kelas, 3)->update(['status' => Pertemuan::DIBATALKAN]);

    $rekap = PresensiMahasiswa::rekapKelas($kelas->id);
    expect($rekap[$a]['persen'])->toBe(100.0)
        ->and($rekap[$b]['persen'])->toBe(50.0)
        ->and($rekap[$c]['persen'])->toBe(0.0)
        ->and($rekap[$c]['izin'])->toBe(1)
        ->and($rekap[$c]['alpa'])->toBe(1)
        ->and($rekap[$a]['dihitung'])->toBe(2);

    expect(PresensiMahasiswa::where('mahasiswa_id', $c)->where('status', 'izin')->value('keterangan'))->toBe('Acara keluarga');

    $this->actingAs($dosen)->get(route('dosen.presensi.kelas', $kelas))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Kelas/PresensiKelas')->has('pertemuan', 16)->has('peserta', 3)->where('minKehadiran', 75));
});

it('rejects attendance for students outside the meeting', function () {
    [$kelas] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $pertemuan = pertemuanKe($kelas, 1);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.presensi.pertemuan.mulai', $pertemuan));
    $orangLain = User::factory()->mahasiswa()->create();

    $this->actingAs($admin)->put(route('admin.presensi.pertemuan.mahasiswa', $pertemuan), [
        'presensi' => [['mahasiswa_id' => $orangLain->mahasiswaProfile->id, 'status' => 'hadir']],
    ])->assertStatus(422);
});

it('only counts meetings a student was registered for', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.presensi.pertemuan.mulai', pertemuanKe($kelas, 1)));
    $this->actingAs($admin)->post(route('admin.presensi.pertemuan.selesai', pertemuanKe($kelas, 1)), ['topik' => 'Pengantar']);

    // Mahasiswa baru masuk sesudah pertemuan 1 selesai.
    $baru = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $baru->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

    $this->actingAs($admin)->post(route('admin.presensi.pertemuan.mulai', pertemuanKe($kelas, 2)));
    $this->actingAs($admin)->put(route('admin.presensi.pertemuan.mahasiswa', pertemuanKe($kelas, 2)), [
        'presensi' => [['mahasiswa_id' => $baru->mahasiswaProfile->id, 'status' => 'hadir']],
    ]);
    $this->actingAs($admin)->post(route('admin.presensi.pertemuan.selesai', pertemuanKe($kelas, 2)), ['topik' => 'Lanjutan']);

    $rekap = PresensiMahasiswa::rekapKelas($kelas->id);
    expect($rekap[$baru->mahasiswaProfile->id]['dihitung'])->toBe(1)
        ->and($rekap[$baru->mahasiswaProfile->id]['persen'])->toBe(100.0)
        ->and($rekap[$mahasiswa[0]->mahasiswaProfile->id]['dihitung'])->toBe(2);
});

it('closes a forgotten meeting an hour after it ends when the page is opened', function () {
    [$kelas] = kelasPresensi();
    Pertemuan::generateUntuk($kelas);
    $pertemuan = pertemuanKe($kelas, 1);
    $dosen = $kelas->dosen->user;

    $this->travelTo('2025-08-04 08:00:00');
    $this->actingAs($dosen)->post(route('dosen.presensi.pertemuan.mulai', $pertemuan));

    $this->travelTo('2025-08-04 10:30:00');
    $this->actingAs($dosen)->get(route('dosen.presensi.index'))->assertOk();
    expect($pertemuan->fresh()->status)->toBe(Pertemuan::BERLANGSUNG);

    $this->travelTo('2025-08-04 11:01:00');
    $this->actingAs($dosen)->get(route('dosen.presensi.index'))->assertOk();
    expect($pertemuan->fresh()->status)->toBe(Pertemuan::SELESAI)
        ->and($pertemuan->fresh()->dosen_keluar_at->format('H:i'))->toBe('10:00');
});

it('locks attendance for lecturers once the academic year is inactive', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $pertemuan = pertemuanKe($kelas, 1);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.presensi.pertemuan.mulai', $pertemuan));
    $kelas->tahunAkademik->update(['status' => false]);
    $isian = ['presensi' => [['mahasiswa_id' => $mahasiswa[0]->mahasiswaProfile->id, 'status' => 'hadir']]];

    $this->actingAs($kelas->dosen->user)->put(route('dosen.presensi.pertemuan.mahasiswa', $pertemuan), $isian)->assertForbidden();
    $this->actingAs($admin)->put(route('admin.presensi.pertemuan.mahasiswa', $pertemuan), $isian)->assertSessionHas('success');
});

it('reschedules a meeting and rejects a clash with another class in the same room', function () {
    [$kelas] = kelasPresensi(0);
    Pertemuan::generateUntuk($kelas);
    $lain = createMateriKelasKuliah($kelas->tahunAkademik);
    $ruang = Ruang::where('kode_ruang', 'R-101')->first();
    Pertemuan::create(['kelas_id' => $lain->id, 'pertemuan_ke' => 1, 'tanggal' => '2025-08-06', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'ruang_id' => $ruang->id, 'dosen_id' => $lain->dosen_id]);
    $dosen = $kelas->dosen->user;
    $pertemuan = pertemuanKe($kelas, 1);
    $isian = ['jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $ruang->id, 'jenis' => 'kuliah', 'catatan' => 'Pengganti libur'];

    $this->actingAs($dosen)->put(route('dosen.presensi.pertemuan.update', $pertemuan), [...$isian, 'tanggal' => '2025-08-06'])->assertSessionHasErrors('tanggal');

    $this->actingAs($dosen)->put(route('dosen.presensi.pertemuan.update', $pertemuan), [...$isian, 'tanggal' => '2025-08-07'])->assertSessionHasNoErrors();
    expect($pertemuan->fresh()->tanggal->toDateString())->toBe('2025-08-07')
        ->and($pertemuan->fresh()->catatan)->toBe('Pengganti libur');
});

it('cancels and restores a meeting that has not started', function () {
    [$kelas] = kelasPresensi(0);
    Pertemuan::generateUntuk($kelas);
    $pertemuan = pertemuanKe($kelas, 2);
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)->put(route('dosen.presensi.pertemuan.batal', $pertemuan), ['catatan' => ''])->assertSessionHasErrors('catatan');
    $this->actingAs($dosen)->put(route('dosen.presensi.pertemuan.batal', $pertemuan), ['catatan' => 'Libur nasional'])->assertSessionHas('success');
    expect($pertemuan->fresh()->status)->toBe(Pertemuan::DIBATALKAN);

    $this->actingAs($dosen)->put(route('dosen.presensi.pertemuan.aktifkan', $pertemuan))->assertSessionHas('success');
    expect($pertemuan->fresh()->status)->toBe(Pertemuan::DIJADWALKAN);
});

it('limits the materi meeting number to the class meeting count', function () {
    [$kelas] = kelasPresensi(0);
    $kelas->update(['jumlah_pertemuan' => 14]);
    $isian = ['judul_materi' => 'Penutup', 'jenis' => 'Materi'];

    $this->actingAs($kelas->dosen->user)->post(route('dosen.kelas-kuliah.materi.store', $kelas), [...$isian, 'pertemuan_ke' => 15])->assertSessionHasErrors('pertemuan_ke');
    $this->actingAs($kelas->dosen->user)->post(route('dosen.kelas-kuliah.materi.store', $kelas), [...$isian, 'pertemuan_ke' => 14])->assertSessionHasNoErrors();
});

it('saves the presensi defaults in the academic settings', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pengaturan-akademik.presensi'), ['jumlah_pertemuan' => 14, 'min_kehadiran_ujian' => 80, 'toleransi_terlambat_menit' => 10, 'durasi_presensi_mandiri_menit' => 20, 'batas_pengajuan_izin_hari' => 2, 'syarat_ujian_aktif' => true])
        ->assertSessionHas('success');

    expect(PengaturanAkademik::current()->only(['jumlah_pertemuan', 'min_kehadiran_ujian', 'toleransi_terlambat_menit', 'durasi_presensi_mandiri_menit', 'batas_pengajuan_izin_hari', 'syarat_ujian_aktif']))
        ->toBe(['jumlah_pertemuan' => 14, 'min_kehadiran_ujian' => 80, 'toleransi_terlambat_menit' => 10, 'durasi_presensi_mandiri_menit' => 20, 'batas_pengajuan_izin_hari' => 2, 'syarat_ujian_aktif' => true]);
});

/**
 * Pertemuan 1 kelasPresensi() yang sedang berlangsung dengan presensi mandiri terbuka (dimulai dosen 08:00).
 *
 * @return array{0: KelasKuliah, 1: list<User>, 2: Pertemuan}
 */
function pertemuanMandiri(int $jumlahMahasiswa = 2): array
{
    [$kelas, $mahasiswa] = kelasPresensi($jumlahMahasiswa);
    Pertemuan::generateUntuk($kelas);
    $pertemuan = pertemuanKe($kelas, 1);

    test()->travelTo('2025-08-04 08:00:00');
    test()->actingAs($kelas->dosen->user)->post(route('dosen.presensi.pertemuan.mulai', $pertemuan));
    test()->actingAs($kelas->dosen->user)->post(route('dosen.presensi.pertemuan.mandiri.buka', $pertemuan))->assertSessionHas('success');

    return [$kelas, $mahasiswa, $pertemuan->fresh()];
}

it('rotates the self check-in code every 30 seconds and accepts it only briefly', function () {
    [, , $pertemuan] = pertemuanMandiri();
    $kode = $pertemuan->kodeUntuk(Pertemuan::periodeKode());

    expect($kode['pin'])->toMatch('/^\d{6}$/')
        ->and($pertemuan->kodeCocok($kode['pin']))->toBeTrue()
        ->and($pertemuan->kodeCocok($kode['token']))->toBeTrue()
        ->and($pertemuan->kodeUntuk(Pertemuan::periodeKode() + 1)['pin'])->not->toBe($kode['pin']);

    $this->travel(60)->seconds();
    expect($pertemuan->kodeCocok($kode['pin']))->toBeTrue();

    $this->travel(40)->seconds();
    expect($pertemuan->kodeCocok($kode['pin']))->toBeFalse();
});

it('lets a student check in with the PIN and marks late arrivals', function () {
    PengaturanAkademik::current()->update(['toleransi_terlambat_menit' => 15, 'durasi_presensi_mandiri_menit' => 30]);
    [$kelas, $mahasiswa, $pertemuan] = pertemuanMandiri();

    $this->travelTo('2025-08-04 08:10:00');
    $this->actingAs($mahasiswa[0])
        ->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => $pertemuan->kodeUntuk(Pertemuan::periodeKode())['pin']])
        ->assertRedirect(route('mahasiswa.presensi'))
        ->assertSessionHas('success');

    $this->travelTo('2025-08-04 08:20:00');
    $this->actingAs($mahasiswa[1])
        ->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => $pertemuan->kodeUntuk(Pertemuan::periodeKode())['token']]);

    $baris = $pertemuan->presensiMahasiswas()->get()->keyBy('mahasiswa_id');
    expect($baris[$mahasiswa[0]->mahasiswaProfile->id]->status)->toBe(PresensiMahasiswa::HADIR)
        ->and($baris[$mahasiswa[0]->mahasiswaProfile->id]->metode)->toBe('pin')
        ->and($baris[$mahasiswa[1]->mahasiswaProfile->id]->status)->toBe(PresensiMahasiswa::TERLAMBAT)
        ->and($baris[$mahasiswa[1]->mahasiswaProfile->id]->metode)->toBe('qr');

    // Presensi kedua tidak mengubah jam presensi pertama.
    $this->actingAs($mahasiswa[0])
        ->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => $pertemuan->kodeUntuk(Pertemuan::periodeKode())['pin']])
        ->assertSessionHas('success', 'Anda sudah tercatat hadir pukul 08:10.');
});

it('rejects wrong codes, closed sessions, and students outside the class', function () {
    [, $mahasiswa, $pertemuan] = pertemuanMandiri();
    $pin = fn () => $pertemuan->kodeUntuk(Pertemuan::periodeKode())['pin'];

    $this->actingAs($mahasiswa[0])
        ->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => $pin() === '000000' ? '111111' : '000000'])
        ->assertSessionHasErrors('kode');

    $orangLain = User::factory()->mahasiswa()->create();
    $this->actingAs($orangLain)
        ->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => $pin()])
        ->assertSessionHasErrors('kode');

    $this->actingAs($pertemuan->kelasKuliah->dosen->user)->delete(route('dosen.presensi.pertemuan.mandiri.tutup', $pertemuan));
    $this->actingAs($mahasiswa[0])
        ->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => $pin()])
        ->assertSessionHasErrors('kode');

    expect($pertemuan->presensiMahasiswas()->whereIn('status', PresensiMahasiswa::DIHITUNG_HADIR)->count())->toBe(0);
});

it('closes self check-in after the configured duration', function () {
    PengaturanAkademik::current()->update(['durasi_presensi_mandiri_menit' => 10]);
    [, $mahasiswa, $pertemuan] = pertemuanMandiri();

    $this->travelTo('2025-08-04 08:11:00');
    $this->actingAs($mahasiswa[0])
        ->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => $pertemuan->kodeUntuk(Pertemuan::periodeKode())['pin']])
        ->assertSessionHasErrors('kode');
});

it('serves the current code to the lecturer screen but hides the secret', function () {
    [$kelas, , $pertemuan] = pertemuanMandiri();

    $this->actingAs($kelas->dosen->user)->getJson(route('dosen.presensi.pertemuan.kode', $pertemuan))
        ->assertOk()
        ->assertJson(['terbuka' => true, 'pin' => $pertemuan->kodeUntuk(Pertemuan::periodeKode())['pin'], 'hadir' => 0, 'total' => 2])
        ->assertJsonPath('url', route('mahasiswa.presensi.masuk', ['pertemuan' => $pertemuan->id, 'k' => $pertemuan->kodeUntuk(Pertemuan::periodeKode())['token']]));

    $this->actingAs(createMateriKelasKuliah()->dosen->user)->getJson(route('dosen.presensi.pertemuan.kode', $pertemuan))->assertForbidden();

    $this->actingAs($kelas->dosen->user)->get(route('dosen.presensi.pertemuan.show', $pertemuan))
        ->assertInertia(fn ($page) => $page->where('mandiriTerbuka', true)->missing('pertemuan.kode_rahasia'));
});

it('flags one device used by several students', function () {
    [$kelas, $mahasiswa, $pertemuan] = pertemuanMandiri(3);
    $pin = fn () => $pertemuan->kodeUntuk(Pertemuan::periodeKode())['pin'];

    // Mahasiswa pertama mendapat penanda perangkat; mahasiswa kedua memakai browser yang sama.
    $perangkat = $this->actingAs($mahasiswa[0])
        ->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => $pin()])
        ->getCookie('presensi_perangkat')->getValue();
    $this->actingAs($mahasiswa[1])->withCookie('presensi_perangkat', $perangkat)
        ->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => $pin()]);
    $this->actingAs($mahasiswa[2])->withCookie('presensi_perangkat', str_repeat('x', 40))
        ->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => $pin()]);

    $presensi = collect($this->actingAs($kelas->dosen->user)->get(route('dosen.presensi.pertemuan.show', $pertemuan))
        ->viewData('page')['props']['presensi'])->keyBy('mahasiswa_id');

    expect($presensi[$mahasiswa[0]->mahasiswaProfile->id]['perangkat_bersama'])->toBeTrue()
        ->and($presensi[$mahasiswa[1]->mahasiswaProfile->id]['perangkat_bersama'])->toBeTrue()
        ->and($presensi[$mahasiswa[2]->mahasiswaProfile->id]['perangkat_bersama'])->toBeFalse()
        ->and($presensi->first())->not->toHaveKey('perangkat');
});

it('shows students their attendance history and the absences they have left', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $admin = User::factory()->admin()->create();
    $id = $mahasiswa[0]->mahasiswaProfile->id;

    foreach ([1 => 'hadir', 2 => 'alpa', 3 => 'izin'] as $ke => $status) {
        $this->actingAs($admin)->post(route('admin.presensi.pertemuan.mulai', pertemuanKe($kelas, $ke)));
        $this->actingAs($admin)->put(route('admin.presensi.pertemuan.mahasiswa', pertemuanKe($kelas, $ke)), ['presensi' => [['mahasiswa_id' => $id, 'status' => $status]]]);
        $this->actingAs($admin)->post(route('admin.presensi.pertemuan.selesai', pertemuanKe($kelas, $ke)), ['topik' => "Topik {$ke}"]);
    }

    // 14 pertemuan kuliah, batas 75% → boleh absen 3; sudah absen 2 (alpa + izin).
    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.presensi'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/Presensi')
            ->where('kelas.0.rekap.persen', 33.3)
            ->where('kelas.0.rencana', 14)
            ->where('kelas.0.sisa_absen', 1)
            ->where('kelas.0.pertemuan.1.presensi.status', 'alpa')
            ->has('kelas.0.pertemuan', 16));
});

it('shows the QR confirmation page only to enrolled students', function () {
    [, $mahasiswa, $pertemuan] = pertemuanMandiri();

    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.presensi.masuk', ['pertemuan' => $pertemuan->id, 'k' => 'abc']))
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/PresensiMasuk')->where('terdaftar', true)->where('terbuka', true)->where('kode', 'abc'));

    $this->actingAs(User::factory()->mahasiswa()->create())->get(route('mahasiswa.presensi.masuk', $pertemuan))
        ->assertInertia(fn ($page) => $page->where('terdaftar', false)->where('pertemuan', null));

    // Membuka tautan saja tidak mencatat presensi.
    expect($pertemuan->presensiMahasiswas()->where('status', 'hadir')->count())->toBe(0);
});

it('exports the class recap as CSV and PDF', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.presensi.pertemuan.mulai', pertemuanKe($kelas, 1)));
    $this->actingAs($admin)->put(route('admin.presensi.pertemuan.mahasiswa', pertemuanKe($kelas, 1)), ['presensi' => [['mahasiswa_id' => $mahasiswa[0]->mahasiswaProfile->id, 'status' => 'hadir']]]);
    $this->actingAs($admin)->post(route('admin.presensi.pertemuan.selesai', pertemuanKe($kelas, 1)), ['topik' => 'Pengantar']);

    $csv = $this->actingAs($kelas->dosen->user)->get(route('dosen.presensi.ekspor', [$kelas, 'format' => 'csv']))->assertOk()->streamedContent();
    $baris = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
    expect($baris[0][2])->toBe('P1')
        ->and($baris[0][9])->toBe('UTS')
        ->and($baris[1][0])->toBe($mahasiswa[0]->mahasiswaProfile->nim)
        ->and($baris[1][2])->toBe('H')
        ->and(end($baris[1]))->toBe('100');

    $this->actingAs($kelas->dosen->user)->get(route('dosen.presensi.ekspor', $kelas))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs(createMateriKelasKuliah()->dosen->user)->get(route('dosen.presensi.ekspor', $kelas))->assertForbidden();
});

/**
 * Selesaikan pertemuan ke-$ke dengan status per mahasiswa (dicatat admin).
 *
 * @param  array<int, string>  $status  mahasiswa_id => status
 */
function selesaikanPertemuan(KelasKuliah $kelas, int $ke, array $status): Pertemuan
{
    $admin = User::factory()->admin()->create();
    $pertemuan = pertemuanKe($kelas, $ke);
    test()->actingAs($admin)->post(route('admin.presensi.pertemuan.mulai', $pertemuan));
    test()->actingAs($admin)->put(route('admin.presensi.pertemuan.mahasiswa', $pertemuan), [
        'presensi' => collect($status)->map(fn ($s, $id) => ['mahasiswa_id' => $id, 'status' => $s])->values()->all(),
    ]);
    test()->actingAs($admin)->post(route('admin.presensi.pertemuan.selesai', $pertemuan), ['topik' => "Topik {$ke}"]);

    return $pertemuan->fresh();
}

beforeEach(fn () => Storage::fake('local'));

it('lets students request leave until the deadline and resubmit after a rejection', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $pertemuan = pertemuanKe($kelas, 1); // Senin 4 Agustus 2025
    $isian = ['pertemuan_id' => $pertemuan->id, 'jenis' => 'sakit', 'alasan' => 'Demam'];

    $this->travelTo('2025-08-06 00:00:01'); // lewat batas 1 hari
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), $isian)->assertSessionHasErrors('pertemuan_id');

    $this->travelTo('2025-08-05 20:00:00');
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), [...$isian, 'lampiran' => [UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf')]])
        ->assertSessionHasNoErrors();
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), $isian)->assertSessionHasErrors('pertemuan_id');

    $pengajuan = PengajuanIzin::firstOrFail();
    expect($pengajuan->status)->toBe(PengajuanIzin::MENUNGGU)->and($pengajuan->lampiran)->toHaveCount(1);
    Storage::disk('local')->assertExists($pengajuan->lampiran[0]);

    $this->actingAs($kelas->dosen->user)->put(route('dosen.presensi.izin.proses', $pengajuan), ['keputusan' => 'ditolak'])->assertSessionHasErrors('catatan_dosen');
    $this->actingAs($kelas->dosen->user)->put(route('dosen.presensi.izin.proses', $pengajuan), ['keputusan' => 'ditolak', 'catatan_dosen' => 'Lampirkan surat dokter yang terbaca'])->assertSessionHas('success');

    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), [...$isian, 'alasan' => 'Demam, surat terlampir'])->assertSessionHasNoErrors();
    expect($pengajuan->fresh()->status)->toBe(PengajuanIzin::MENUNGGU)->and($pengajuan->fresh()->catatan_dosen)->toBeNull();
});

it('rejects leave attachments that are not documents or photos', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $this->travelTo('2025-08-04 12:00:00');

    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), [
        'pertemuan_id' => pertemuanKe($kelas, 1)->id, 'jenis' => 'izin', 'alasan' => 'Acara keluarga',
        'lampiran' => [UploadedFile::fake()->create('surat.html', 5, 'text/html')],
    ])->assertSessionHasErrors('lampiran.0');
});

it('marks the attendance when the lecturer approves, even before the meeting starts', function () {
    [$kelas, $mahasiswa] = kelasPresensi(2);
    Pertemuan::generateUntuk($kelas);
    $pertemuan = pertemuanKe($kelas, 2); // 11 Agustus
    $id = $mahasiswa[0]->mahasiswaProfile->id;
    $this->travelTo('2025-08-08 09:00:00');
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), ['pertemuan_id' => $pertemuan->id, 'jenis' => 'izin', 'alasan' => 'Lomba mewakili kampus']);
    $pengajuan = PengajuanIzin::firstOrFail();

    $this->actingAs(createMateriKelasKuliah()->dosen->user)->put(route('dosen.presensi.izin.proses', $pengajuan), ['keputusan' => 'disetujui'])->assertForbidden();
    $this->actingAs($kelas->dosen->user)->get(route('dosen.presensi.izin.index'))->assertInertia(fn ($page) => $page->component('Kelas/PengajuanIzin')->where('pengajuan.total', 1));
    $this->actingAs($kelas->dosen->user)->put(route('dosen.presensi.izin.proses', $pengajuan), ['keputusan' => 'disetujui'])->assertSessionHas('success');

    // Saat pertemuan dimulai, status izin tidak ditimpa menjadi Alpa.
    $this->travelTo('2025-08-11 08:00:00');
    $this->actingAs($kelas->dosen->user)->post(route('dosen.presensi.pertemuan.mulai', $pertemuan));
    expect($pertemuan->presensiMahasiswas()->where('mahasiswa_id', $id)->value('status'))->toBe(PresensiMahasiswa::IZIN)
        ->and($pertemuan->presensiMahasiswas()->where('mahasiswa_id', $mahasiswa[1]->mahasiswaProfile->id)->value('status'))->toBe(PresensiMahasiswa::ALPA);
});

it('limits leave attachments to the student, the lecturer, and admin', function () {
    [$kelas, $mahasiswa] = kelasPresensi(2);
    Pertemuan::generateUntuk($kelas);
    $this->travelTo('2025-08-04 12:00:00');
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), [
        'pertemuan_id' => pertemuanKe($kelas, 1)->id, 'jenis' => 'sakit', 'alasan' => 'Demam',
        'lampiran' => [UploadedFile::fake()->image('surat.jpg')],
    ]);
    $url = route('berkas.izin', [PengajuanIzin::firstOrFail(), 0]);

    $this->actingAs($mahasiswa[0])->get($url)->assertOk();
    $this->actingAs($kelas->dosen->user)->get($url)->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    $this->actingAs($mahasiswa[1])->get($url)->assertForbidden();
    $this->actingAs(createMateriKelasKuliah()->dosen->user)->get($url)->assertForbidden();
});

it('computes exam eligibility from meetings before the UTS and honours dispensations', function () {
    [$kelas, $mahasiswa] = kelasPresensi(2);
    Pertemuan::generateUntuk($kelas);
    [$a, $b] = array_map(fn (User $u) => $u->mahasiswaProfile->id, $mahasiswa);

    // Pertemuan 1–4: A hadir semua; B hadir sekali, izin, sakit, alpa (25%). Pertemuan 9 (sesudah UTS): B hadir.
    foreach ([1 => 'hadir', 2 => 'izin', 3 => 'sakit', 4 => 'alpa'] as $ke => $statusB) {
        selesaikanPertemuan($kelas, $ke, [$a => 'hadir', $b => $ke === 1 ? 'hadir' : $statusB]);
    }
    selesaikanPertemuan($kelas, 9, [$a => 'hadir', $b => 'hadir']);

    $syarat = SyaratUjian::untukKelas($kelas);
    expect($syarat['aktif'])->toBeFalse()
        ->and($syarat['peserta'][$b]['uts']['persen'])->toBe(25.0)
        ->and($syarat['peserta'][$b]['uts']['memenuhi'])->toBeNull()
        ->and($syarat['peserta'][$b]['uas']['persen'])->toBe(40.0);

    PengaturanAkademik::current()->update(['syarat_ujian_aktif' => true]);
    $syarat = SyaratUjian::untukKelas($kelas);
    expect($syarat['peserta'][$a]['uts']['memenuhi'])->toBeTrue()
        ->and($syarat['peserta'][$b]['uts']['memenuhi'])->toBeFalse()
        ->and($syarat['jadwal']['uts']->pertemuan_ke)->toBe(8);

    // Kaprodi (di sini sekaligus pengampu) memberi dispensasi UTS.
    $this->actingAs($kelas->dosen->user)->post(route('dosen.presensi.dispensasi.simpan', $kelas), ['mahasiswa_id' => $b, 'jenis' => ['uts'], 'alasan' => 'Rawat inap, surat RS'])->assertSessionHas('success');
    $syarat = SyaratUjian::untukKelas($kelas);
    expect($syarat['peserta'][$b]['uts']['memenuhi'])->toBeTrue()
        ->and($syarat['peserta'][$b]['uts']['dispensasi']['alasan'])->toBe('Rawat inap, surat RS')
        ->and($syarat['peserta'][$b]['uas']['memenuhi'])->toBeFalse();

    $this->actingAs($mahasiswa[1])->get(route('mahasiswa.presensi'))
        ->assertInertia(fn ($page) => $page->where('kelas.0.ujian.syarat.uts.memenuhi', true)->where('kelas.0.ujian.uts.pertemuan_ke', 8));

    $this->actingAs($kelas->dosen->user)->get(route('dosen.presensi.peserta-ujian', [$kelas, 'jenis' => 'uas']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('allows only admin and the kaprodi to grant dispensations', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    $b = $mahasiswa[0]->mahasiswaProfile->id;
    $isian = ['mahasiswa_id' => $b, 'jenis' => ['uts', 'uas'], 'alasan' => 'Tugas negara'];
    $kaprodi = User::factory()->dosen()->create();
    $kelas->mataKuliah->prodi->update(['kaprodi' => $kaprodi->dosenProfile->id]);

    // Pengampu yang bukan kaprodi tidak bisa memberi dispensasi.
    $this->actingAs($kelas->dosen->user)->post(route('dosen.presensi.dispensasi.simpan', $kelas), $isian)->assertForbidden();

    $this->actingAs($kaprodi)->post(route('dosen.presensi.dispensasi.simpan', $kelas), $isian)->assertSessionHas('success');
    expect(DispensasiUjian::where('mahasiswa_id', $b)->count())->toBe(2);

    $this->actingAs(User::factory()->admin()->create())->delete(route('admin.presensi.dispensasi.hapus', DispensasiUjian::where('jenis', 'uas')->first()))->assertSessionHas('success');
    expect(DispensasiUjian::where('mahasiswa_id', $b)->pluck('jenis')->all())->toBe(['uts']);

    $this->actingAs($kaprodi)->post(route('dosen.presensi.dispensasi.simpan', $kelas), [...$isian, 'mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id])
        ->assertSessionHasErrors('mahasiswa_id');
});

it('lets the kaprodi view but not change attendance of classes in the prodi', function () {
    [$kelas] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $kaprodi = User::factory()->dosen()->create();
    $kelas->mataKuliah->prodi->update(['kaprodi' => $kaprodi->dosenProfile->id]);
    createMateriKelasKuliah($kelas->tahunAkademik); // kelas prodi lain tidak boleh ikut terlihat

    $this->actingAs($kaprodi)->get(route('dosen.presensi.index'))->assertInertia(fn ($page) => $page->where('kelas.total', 0)->where('bisaLingkupProdi', true));
    $this->actingAs($kaprodi)->get(route('dosen.presensi.index', ['lingkup' => 'prodi']))->assertInertia(fn ($page) => $page->where('kelas.total', 1)->where('lingkup', 'prodi')->has('kelasOptions', 1)->has('prodiOptions', 1));
    $this->actingAs($kaprodi)->get(route('dosen.presensi.kelas', $kelas))->assertInertia(fn ($page) => $page->where('bisaKelola', false)->where('bisaDispensasi', true));
    $this->actingAs($kaprodi)->get(route('dosen.presensi.pertemuan.show', pertemuanKe($kelas, 1)))->assertInertia(fn ($page) => $page->where('bisaKelola', false));
    $this->actingAs($kaprodi)->post(route('dosen.presensi.pertemuan.mulai', pertemuanKe($kelas, 1)))->assertForbidden();
    $this->actingAs($kaprodi)->post(route('dosen.presensi.generate', $kelas))->assertForbidden();
});

it('lets a substitute lecturer run only the assigned meeting', function () {
    [$kelas] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $pengganti = User::factory()->dosen()->create();
    $pertemuan = pertemuanKe($kelas, 1);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.presensi.pertemuan.update', $pertemuan), [
        'tanggal' => '2025-08-04', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $pertemuan->ruang_id, 'jenis' => 'kuliah', 'dosen_id' => $pengganti->dosenProfile->id,
    ])->assertSessionHasNoErrors();

    $this->travelTo('2025-08-04 08:00:00');
    $this->actingAs($pengganti)->get(route('dosen.presensi.index'))->assertInertia(fn ($page) => $page->has('hariIni', 1));
    $this->actingAs($pengganti)->get(route('dosen.presensi.pertemuan.show', $pertemuan))->assertOk()->assertInertia(fn ($page) => $page->where('bisaKelola', true)->where('bisaAturJadwal', false));
    $this->actingAs($pengganti)->post(route('dosen.presensi.pertemuan.mulai', $pertemuan))->assertSessionHas('success');

    expect($pertemuan->fresh()->status)->toBe(Pertemuan::BERLANGSUNG);
    $this->actingAs($pengganti)->get(route('dosen.presensi.kelas', $kelas))->assertForbidden();
    $this->actingAs($pengganti)->post(route('dosen.presensi.pertemuan.mulai', pertemuanKe($kelas, 2)))->assertForbidden();
    $this->actingAs($pengganti)->put(route('dosen.presensi.pertemuan.batal', pertemuanKe($kelas, 2)), ['catatan' => 'x'])->assertForbidden();
});

it('reports lecturer attendance per class', function () {
    PengaturanAkademik::current()->update(['toleransi_terlambat_menit' => 15]);
    [$kelas] = kelasPresensi(0);
    Pertemuan::generateUntuk($kelas);
    $dosen = $kelas->dosen->user;

    foreach ([1 => ['2025-08-04 08:05:00', 'Pengantar'], 2 => ['2025-08-11 08:30:00', 'Variabel']] as $ke => [$waktu, $topik]) {
        $this->travelTo($waktu);
        $this->actingAs($dosen)->post(route('dosen.presensi.pertemuan.mulai', pertemuanKe($kelas, $ke)));
        $this->actingAs($dosen)->post(route('dosen.presensi.pertemuan.selesai', pertemuanKe($kelas, $ke)), ['topik' => $topik]);
    }
    pertemuanKe($kelas, 3)->update(['status' => Pertemuan::DIBATALKAN]);
    pertemuanKe($kelas, 4)->update(['status' => Pertemuan::SELESAI]); // ditutup tanpa jurnal

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.presensi.laporan-dosen', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->component('Kelas/LaporanKehadiranDosen')
            ->where('baris.0.terlaksana', 3)
            ->where('baris.0.dibatalkan', 1)
            ->where('baris.0.terlambat', 1)
            ->where('baris.0.tanpa_jurnal', 1)
            ->where('baris.0.rencana', 16));

    $csv = $this->actingAs($admin)->get(route('admin.presensi.laporan-dosen', ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'format' => 'csv']))->streamedContent();
    expect($csv)->toContain('Masuk Terlambat')->toContain($kelas->kode_kelas);

    $this->actingAs($dosen)->get('/admin/presensi/laporan-dosen')->assertForbidden();
});

it('warns students and lecturers on their dashboards', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $id = $mahasiswa[0]->mahasiswaProfile->id;
    foreach ([1, 2] as $ke) {
        selesaikanPertemuan($kelas, $ke, [$id => 'alpa']);
    }

    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.dashboard'))
        ->assertInertia(fn ($page) => $page->has('peringatanPresensi', 1)->where('peringatanPresensi.0.persen', 0));

    $this->actingAs($kelas->dosen->user)->get(route('dosen.dashboard'))
        ->assertInertia(fn ($page) => $page->where('presensiDosen.mahasiswaBerisiko', 1)->where('presensiDosen.izinMenunggu', 0));
});

// ---- Perbaikan bug presensi ----

it('keeps the UAS last and moves the UTS when the meeting count shrinks', function () {
    [$kelas] = kelasPresensi(0);
    Pertemuan::generateUntuk($kelas);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.presensi.jumlah', $kelas), ['jumlah_pertemuan' => 14])->assertSessionHasNoErrors();
    expect(Pertemuan::where('kelas_id', $kelas->id)->count())->toBe(14)
        ->and(pertemuanKe($kelas, 14)->jenis)->toBe(Pertemuan::UAS)
        ->and(pertemuanKe($kelas, 8)->jenis)->toBe(Pertemuan::UTS)
        ->and(Pertemuan::where('kelas_id', $kelas->id)->where('jenis', Pertemuan::UAS)->count())->toBe(1);

    // Dikurangi sampai UTS ikut terbuang: UTS pindah ke tengah.
    $this->actingAs($admin)->put(route('admin.presensi.jumlah', $kelas), ['jumlah_pertemuan' => 6])->assertSessionHasNoErrors();
    expect(pertemuanKe($kelas, 6)->jenis)->toBe(Pertemuan::UAS)
        ->and(pertemuanKe($kelas, 3)->jenis)->toBe(Pertemuan::UTS)
        ->and(Pertemuan::where('kelas_id', $kelas->id)->whereIn('jenis', [Pertemuan::UTS, Pertemuan::UAS])->count())->toBe(2);
});

it('moves the UAS to the new last meeting and creates the extra meetings when the count grows', function () {
    [$kelas] = kelasPresensi(0);
    Pertemuan::generateUntuk($kelas);

    $this->actingAs(User::factory()->admin()->create())->put(route('admin.presensi.jumlah', $kelas), ['jumlah_pertemuan' => 18])->assertSessionHasNoErrors();

    $pertemuan = Pertemuan::where('kelas_id', $kelas->id)->orderBy('pertemuan_ke')->get();
    expect($pertemuan)->toHaveCount(18)
        ->and($pertemuan->last()->jenis)->toBe(Pertemuan::UAS)
        ->and(pertemuanKe($kelas, 16)->jenis)->toBe(Pertemuan::KULIAH)
        ->and($pertemuan->where('jenis', Pertemuan::UAS))->toHaveCount(1)
        ->and($pertemuan->last()->tanggal->gt(pertemuanKe($kelas, 16)->tanggal))->toBeTrue();
});

it('refuses to drop meetings that have leave requests or attendance, with an accurate message', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $admin = User::factory()->admin()->create();
    $this->travelTo('2025-11-10 09:00:00');
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), ['pertemuan_id' => pertemuanKe($kelas, 15)->id, 'jenis' => 'izin', 'alasan' => 'Lomba']);
    expect(PengajuanIzin::count())->toBe(1);

    $this->actingAs($admin)->put(route('admin.presensi.jumlah', $kelas), ['jumlah_pertemuan' => 14])
        ->assertSessionHasErrors(['jumlah_pertemuan' => 'Pertemuan ke-15 sudah punya presensi atau pengajuan izin mahasiswa, jadi jumlah pertemuan minimal 15.']);
    expect(PengajuanIzin::count())->toBe(1)->and(Pertemuan::where('kelas_id', $kelas->id)->count())->toBe(16);
});

it('detects clashes with the weekly schedule of classes that have no meetings yet', function () {
    [$kelas] = kelasPresensi(0);
    Pertemuan::generateUntuk($kelas);
    $lain = createMateriKelasKuliah($kelas->tahunAkademik);
    $ruang = Ruang::where('kode_ruang', 'R-101')->first();
    Jadwal::create(['kelas_id' => $lain->id, 'hari' => 'Rabu', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $ruang->id]);
    $isian = ['jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'ruang_id' => $ruang->id, 'jenis' => 'kuliah'];

    // 2025-08-06 adalah hari Rabu.
    $this->actingAs($kelas->dosen->user)->put(route('dosen.presensi.pertemuan.update', pertemuanKe($kelas, 1)), [...$isian, 'tanggal' => '2025-08-06'])
        ->assertSessionHasErrors(['tanggal' => 'Bentrok dengan jadwal mingguan kelas '.$lain->kode_kelas.' (Rabu 08:00–10:00) pada ruang atau dosen yang sama.']);

    $this->actingAs($kelas->dosen->user)->put(route('dosen.presensi.pertemuan.update', pertemuanKe($kelas, 1)), [...$isian, 'tanggal' => '2025-08-07'])
        ->assertSessionHasNoErrors();
    expect(pertemuanKe($kelas, 1)->jadwal_manual)->toBeTrue();
});

it('deletes an unused class together with its empty meetings, but keeps classes with attendance', function () {
    $admin = User::factory()->admin()->create();
    [$kosong] = kelasPresensi(0);
    Pertemuan::generateUntuk($kosong);

    $this->actingAs($admin)->delete(route('admin.kelas-kuliah.destroy', $kosong))->assertSessionHas('success');
    expect(KelasKuliah::whereKey($kosong->id)->exists())->toBeFalse()->and(Pertemuan::where('kelas_id', $kosong->id)->exists())->toBeFalse();

    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    selesaikanPertemuan($kelas, 1, [$mahasiswa[0]->mahasiswaProfile->id => 'hadir']);
    Krs::where('kelas_id', $kelas->id)->first()->cancel();

    $this->actingAs($admin)->delete(route('admin.kelas-kuliah.destroy', $kelas))
        ->assertSessionHas('error', 'Kelas Kuliah tidak dapat dihapus karena sudah memiliki data presensi (pertemuan berjalan, presensi, pengajuan izin, atau dispensasi).');
    $this->actingAs($admin)->delete(route('admin.users.mahasiswa.destroy', $mahasiswa[0]))
        ->assertSessionHas('error', 'Mahasiswa tidak dapat dihapus karena sudah memiliki riwayat presensi atau pengajuan izin.');
});

it('applies weekly schedule changes to upcoming meetings but not to held or manually moved ones', function () {
    [$kelas] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas); // Senin 08:00–10:00 di R-101, pertemuan 1 = 4 Agustus 2025
    $jadwal = $kelas->jadwals()->first();
    $ruangBaru = Ruang::create(['kode_ruang' => 'R-205', 'nama_ruang' => 'Ruang 205', 'kapasitas' => 40]);
    $admin = User::factory()->admin()->create();
    selesaikanPertemuan($kelas, 1, []);
    pertemuanKe($kelas, 5)->update(['tanggal' => '2025-09-03', 'jadwal_manual' => true]);

    $this->travelTo('2025-08-20 12:00:00'); // pertemuan 1–3 sudah lewat
    $this->actingAs($admin)->get(route('admin.kelas-kuliah.jadwal.edit', [$kelas, $jadwal]))
        ->assertInertia(fn ($page) => $page->where('pertemuanTerkait', 12));

    $this->actingAs($admin)->put(route('admin.kelas-kuliah.jadwal.update', [$kelas, $jadwal]), [
        'hari' => 'Rabu', 'jam_mulai' => '10:00', 'jam_akhir' => '12:00', 'ruang_id' => $ruangBaru->id, 'terapkan_ke_pertemuan' => true,
    ])->assertSessionHas('jadwal_success');

    $p4 = pertemuanKe($kelas, 4);
    expect(pertemuanKe($kelas, 1)->tanggal->toDateString())->toBe('2025-08-04')
        ->and(substr(pertemuanKe($kelas, 1)->jam_mulai, 0, 5))->toBe('08:00')
        ->and($p4->tanggal->toDateString())->toBe('2025-08-27')
        ->and(substr($p4->jam_mulai, 0, 5))->toBe('10:00')
        ->and($p4->ruang_id)->toBe($ruangBaru->id)
        ->and(pertemuanKe($kelas, 5)->tanggal->toDateString())->toBe('2025-09-03')
        ->and(substr(pertemuanKe($kelas, 5)->jam_mulai, 0, 5))->toBe('08:00');
});

it('can leave meetings untouched when the schedule change should not be applied', function () {
    [$kelas] = kelasPresensi(0);
    Pertemuan::generateUntuk($kelas);
    $jadwal = $kelas->jadwals()->first();
    $this->travelTo('2025-08-01 12:00:00');

    $this->actingAs(User::factory()->admin()->create())->put(route('admin.kelas-kuliah.jadwal.update', [$kelas, $jadwal]), [
        'hari' => 'Selasa', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $jadwal->ruang_id, 'terapkan_ke_pertemuan' => false,
    ]);
    expect(pertemuanKe($kelas, 1)->tanggal->toDateString())->toBe('2025-08-04');

    // Tombol "Susun ulang dari jadwal" di halaman presensi kelas.
    $this->actingAs($kelas->dosen->user)->post(route('dosen.presensi.susun-ulang', $kelas))->assertSessionHas('success');
    expect(pertemuanKe($kelas, 1)->tanggal->toDateString())->toBe('2025-08-05');
});

it('shifts upcoming meetings when the academic year start date changes', function () {
    [$kelas] = kelasPresensi(0);
    Pertemuan::generateUntuk($kelas);
    $tahun = $kelas->tahunAkademik;
    $this->travelTo('2025-07-20 12:00:00');

    $this->actingAs(User::factory()->admin()->create())->put(route('admin.tahun-akademik.update', $tahun), [
        'tahun' => $tahun->tahun, 'semester' => $tahun->semester, 'tanggal_mulai' => '2025-08-08', 'tanggal_akhir' => '2026-01-31',
        'tanggal_krs_awal' => '2025-08-01', 'tanggal_krs_akhir' => '2025-08-14', 'status' => true,
    ])->assertSessionHas('success');

    expect(pertemuanKe($kelas, 1)->tanggal->toDateString())->toBe('2025-08-11');
});

it('keeps a Hadir status when a leave request for the same meeting is approved', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $id = $mahasiswa[0]->mahasiswaProfile->id;
    $this->travelTo('2025-08-03 12:00:00');
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), ['pertemuan_id' => pertemuanKe($kelas, 1)->id, 'jenis' => 'izin', 'alasan' => 'Rencana acara keluarga']);
    $this->travelTo('2025-08-04 12:00:00');
    selesaikanPertemuan($kelas, 1, [$id => 'hadir']);

    $this->flushSession();
    $this->app['auth']->forgetGuards();
    $this->actingAs($kelas->dosen->user)->put(route('dosen.presensi.izin.proses', PengajuanIzin::firstOrFail()), ['keputusan' => 'disetujui'])
        ->assertSessionHas('success', 'Pengajuan disetujui. Mahasiswa sudah tercatat hadir, jadi status presensinya tetap Hadir.');

    expect(pertemuanKe($kelas, 1)->presensiMahasiswas()->where('mahasiswa_id', $id)->value('status'))->toBe(PresensiMahasiswa::HADIR)
        ->and(PengajuanIzin::first()->status)->toBe(PengajuanIzin::DISETUJUI)
        ->and(PengajuanIzin::first()->catatan_dosen)->toBe('Mahasiswa tercatat hadir; status presensi tidak diubah.');
});

it('keeps the previous attachment when a rejected request is resubmitted without a new file', function () {
    [$kelas, $mahasiswa] = kelasPresensi(1);
    Pertemuan::generateUntuk($kelas);
    $this->travelTo('2025-08-04 12:00:00');
    $isian = ['pertemuan_id' => pertemuanKe($kelas, 1)->id, 'jenis' => 'sakit', 'alasan' => 'Demam'];
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), [...$isian, 'lampiran' => [UploadedFile::fake()->create('surat.pdf', 50, 'application/pdf')]]);
    $pengajuan = PengajuanIzin::firstOrFail();
    $berkas = $pengajuan->lampiran[0];
    $this->actingAs($kelas->dosen->user)->put(route('dosen.presensi.izin.proses', $pengajuan), ['keputusan' => 'ditolak', 'catatan_dosen' => 'Sebutkan tanggal sakit']);

    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.presensi.izin'), [...$isian, 'alasan' => 'Demam sejak 3 Agustus'])->assertSessionHasNoErrors();

    expect($pengajuan->fresh()->lampiran)->toBe([$berkas]);
    Storage::disk('local')->assertExists($berkas);
});
