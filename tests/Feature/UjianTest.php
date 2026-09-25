<?php

use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\Ruang;
use App\Models\Ujian;
use App\Models\User;

/**
 * Kelas (TA 2025/2026 Ganjil, jadwal Senin 08:00–10:00 di R-101) dengan pertemuan sudah dibuat.
 *
 * @return array{0: KelasKuliah, 1: list<User>}
 */
function kelasUjian(int $jumlahMahasiswa = 2, string $ruang = 'R-101'): array
{
    $kelas = createMateriKelasKuliah();
    $r = Ruang::firstOrCreate(['kode_ruang' => $ruang], ['nama_ruang' => 'Ruang '.$ruang, 'kapasitas' => 40]);
    Jadwal::create(['kelas_id' => $kelas->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $r->id]);
    $mahasiswa = [];
    for ($i = 0; $i < $jumlahMahasiswa; $i++) {
        $mahasiswa[] = $user = User::factory()->mahasiswa()->create();
        Krs::create(['mahasiswa_id' => $user->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    }
    Pertemuan::generateUntuk($kelas->fresh());

    return [$kelas->fresh(), $mahasiswa];
}

function isianUjian(array $ubah = []): array
{
    return [
        'mode' => 'tatap_muka', 'tanggal' => '2025-10-06', 'jam_mulai' => '13:00', 'jam_akhir' => '15:00',
        'ruang_id' => Ruang::where('kode_ruang', 'R-101')->value('id'), 'pengawas' => 'Pak Budi', 'petunjuk' => 'Buku tertutup', 'status' => 'draf',
        ...$ubah,
    ];
}

it('creates draft exam schedules for all classes from their UTS meetings', function () {
    [$kelas] = kelasUjian();
    $tanpaPertemuan = createMateriKelasKuliah($kelas->tahunAkademik);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.ujian.buat-massal'), ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'jenis' => 'uts'])
        ->assertSessionHas('success', '1 jadwal UTS dibuat sebagai draf dari pertemuan UTS kelas. 1 kelas belum punya pertemuan UTS; buat jadwalnya satu per satu.');

    $ujian = Ujian::firstOrFail();
    $uts = Pertemuan::where('kelas_id', $kelas->id)->where('jenis', 'uts')->first();
    expect($ujian->only(['kelas_id', 'jenis', 'mode', 'status']))->toBe(['kelas_id' => $kelas->id, 'jenis' => 'uts', 'mode' => 'tatap_muka', 'status' => 'draf'])
        ->and($ujian->tanggal->toDateString())->toBe($uts->tanggal->toDateString())
        ->and($ujian->ruang_id)->toBe($uts->ruang_id)
        ->and(Ujian::where('kelas_id', $tanpaPertemuan->id)->exists())->toBeFalse();

    // Dijalankan lagi: kelas yang sudah punya jadwal tidak dibuatkan ulang.
    $this->actingAs($admin)->post(route('admin.ujian.buat-massal'), ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'jenis' => 'uts']);
    expect(Ujian::count())->toBe(1);
});

it('saves a schedule and makes the UTS meeting follow it', function () {
    [$kelas] = kelasUjian();
    $ruang2 = Ruang::create(['kode_ruang' => 'R-202', 'nama_ruang' => 'Ruang 202', 'kapasitas' => 40]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.ujian.store'), [...isianUjian(['ruang_id' => $ruang2->id]), 'kelas_id' => $kelas->id, 'jenis' => 'uts'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Jadwal ujian disimpan. Pertemuan UTS kelas ikut disesuaikan.');

    $uts = Pertemuan::where('kelas_id', $kelas->id)->where('jenis', 'uts')->first();
    expect($uts->tanggal->toDateString())->toBe('2025-10-06')
        ->and(substr($uts->jam_mulai, 0, 5))->toBe('13:00')
        ->and($uts->ruang_id)->toBe($ruang2->id)
        ->and($uts->jadwal_manual)->toBeTrue();

    // Jadi online: ruang dikosongkan di ujian dan pertemuan.
    $ujian = Ujian::firstOrFail();
    $this->actingAs($admin)->put(route('admin.ujian.update', $ujian), isianUjian(['mode' => 'online_soal', 'ruang_id' => $ruang2->id]))->assertSessionHasNoErrors();
    expect($ujian->fresh()->ruang_id)->toBeNull()->and($uts->fresh()->ruang_id)->toBeNull();

    // Kelas & jenis tidak bisa diubah lewat edit; satu kelas satu jadwal per jenis.
    $this->actingAs($admin)->put(route('admin.ujian.update', $ujian), [...isianUjian(), 'jenis' => 'uas'])->assertSessionHasErrors('jenis');
    $this->actingAs($admin)->post(route('admin.ujian.store'), [...isianUjian(), 'kelas_id' => $kelas->id, 'jenis' => 'uts'])->assertSessionHasErrors('jenis');
});

it('validates the exam date, room, and schedule clashes', function () {
    [$kelas, $mahasiswa] = kelasUjian(1);
    [$lain] = kelasUjian(0, 'R-303');
    $admin = User::factory()->admin()->create();
    $baru = fn (array $ubah) => $this->actingAs($admin)->post(route('admin.ujian.store'), [...isianUjian($ubah), 'kelas_id' => $kelas->id, 'jenis' => 'uts']);

    $baru(['tanggal' => '2026-03-01'])->assertSessionHasErrors(['tanggal' => 'Tanggal ujian harus berada dalam tahun akademik kelas.']);
    $baru(['ruang_id' => null])->assertSessionHasErrors(['ruang_id' => 'Ruang wajib diisi untuk ujian tatap muka.']);

    // Ruang R-303 dipakai kuliah kelas lain Senin 6 Okt 08:00–10:00.
    $baru(['ruang_id' => Ruang::where('kode_ruang', 'R-303')->value('id'), 'jam_mulai' => '09:00', 'jam_akhir' => '11:00'])->assertSessionHasErrors('ruang_id');

    // Mahasiswa kelas ini juga mengambil kelas lain yang ujiannya di jam yang sama.
    Krs::create(['mahasiswa_id' => $mahasiswa[0]->mahasiswaProfile->id, 'kelas_id' => $lain->id, 'status' => 'Aktif']);
    Ujian::create([...isianUjian(['mode' => 'online_berkas', 'ruang_id' => null]), 'kelas_id' => $lain->id, 'jenis' => 'uts']);
    $baru([])->assertSessionHasErrors(['tanggal' => '1 mahasiswa kelas ini juga punya ujian lain pada jam yang sama. Ubah jadwal, atau centang "Tetap simpan" bila memang disengaja.']);
    $baru(['abaikan_bentrok_mahasiswa' => true])->assertSessionHasNoErrors();
});

it('publishes drafts and shows only published exams of the student classes', function () {
    [$kelas, $mahasiswa] = kelasUjian(1);
    [$lain] = kelasUjian(0, 'R-303');
    $admin = User::factory()->admin()->create();
    Ujian::create([...isianUjian(), 'kelas_id' => $kelas->id, 'jenis' => 'uts']);
    Ujian::create([...isianUjian(['tanggal' => '2026-01-12']), 'kelas_id' => $kelas->id, 'jenis' => 'uas']);
    Ujian::create([...isianUjian(['ruang_id' => Ruang::where('kode_ruang', 'R-303')->value('id')]), 'kelas_id' => $lain->id, 'jenis' => 'uts', 'status' => 'terbit']);

    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.ujian'))->assertInertia(fn ($page) => $page->component('Mahasiswa/Ujian')->has('ujians', 0));

    $this->flushSession();
    $this->app['auth']->forgetGuards();
    $this->actingAs($admin)->put(route('admin.ujian.terbitkan'), ['ids' => [Ujian::where('jenis', 'uts')->where('kelas_id', $kelas->id)->value('id')]])
        ->assertSessionHas('success', '1 jadwal ujian diterbitkan dan kini tampil ke mahasiswa.');

    $this->flushSession();
    $this->app['auth']->forgetGuards();
    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.ujian'))
        ->assertInertia(fn ($page) => $page->has('ujians', 1)->where('ujians.0.jenis', 'uts')->where('ujians.0.kode_kelas', $kelas->kode_kelas)->where('ujians.0.syarat.memenuhi', null));

    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.ujian.kartu', ['jenis' => 'uts', 'tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.ujian.kartu', ['jenis' => 'uas', 'tahun_akademik_id' => $kelas->tahun_akademik_id]))->assertNotFound();
});

it('shows exam eligibility on the student schedule when the rule is on', function () {
    [$kelas, $mahasiswa] = kelasUjian(1);
    PengaturanAkademik::current()->update(['syarat_ujian_aktif' => true]);
    $id = $mahasiswa[0]->mahasiswaProfile->id;
    $admin = User::factory()->admin()->create();
    foreach ([1, 2] as $ke) {
        $p = Pertemuan::where('kelas_id', $kelas->id)->where('pertemuan_ke', $ke)->first();
        $this->actingAs($admin)->post(route('admin.presensi.pertemuan.mulai', $p));
        $this->actingAs($admin)->put(route('admin.presensi.pertemuan.mahasiswa', $p), ['presensi' => [['mahasiswa_id' => $id, 'status' => 'alpa']]]);
        $this->actingAs($admin)->post(route('admin.presensi.pertemuan.selesai', $p), ['topik' => 'x']);
    }
    Ujian::create([...isianUjian(), 'kelas_id' => $kelas->id, 'jenis' => 'uts', 'status' => 'terbit']);

    $this->flushSession();
    $this->app['auth']->forgetGuards();
    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.ujian'))
        ->assertInertia(fn ($page) => $page->where('ujians.0.syarat.memenuhi', false)->where('ujians.0.syarat.persen', 0));
});

it('lets lecturers see exams of their own classes, including drafts', function () {
    [$kelas] = kelasUjian(0);
    [$lain] = kelasUjian(0, 'R-303');
    Ujian::create([...isianUjian(), 'kelas_id' => $kelas->id, 'jenis' => 'uts']);
    Ujian::create([...isianUjian(['ruang_id' => Ruang::where('kode_ruang', 'R-303')->value('id')]), 'kelas_id' => $lain->id, 'jenis' => 'uts']);

    $this->actingAs($kelas->dosen->user)->get(route('dosen.ujian.index'))
        ->assertInertia(fn ($page) => $page->component('Dosen/Ujian')->has('ujians', 1)->where('ujians.0.status', 'draf'));
});

it('keeps exam administration for admin only', function () {
    [$kelas, $mahasiswa] = kelasUjian(1);
    $this->actingAs($kelas->dosen->user)->get(route('admin.ujian.index'))->assertForbidden();
    $this->actingAs($kelas->dosen->user)->post(route('admin.ujian.store'), [...isianUjian(), 'kelas_id' => $kelas->id, 'jenis' => 'uts'])->assertForbidden();
    $this->actingAs($mahasiswa[0])->get(route('dosen.ujian.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.ujian.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Ujian'));
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.ujian.create'))->assertOk()->assertInertia(fn ($page) => $page->component('Admin/UjianForm'));
});

it('removes the exam schedule together with an unused class', function () {
    [$kelas] = kelasUjian(0);
    Ujian::create([...isianUjian(), 'kelas_id' => $kelas->id, 'jenis' => 'uts']);

    $this->actingAs(User::factory()->admin()->create())->delete(route('admin.kelas-kuliah.destroy', $kelas))->assertSessionHas('success');
    expect(Ujian::count())->toBe(0);
});
