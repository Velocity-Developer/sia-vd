<?php

use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Ruang;
use App\Models\Ujian;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

// ---- Tahap 2: online unggah berkas ----

/**
 * Ujian UTS mode unggah berkas, terbit, Senin 6 Okt 2025 13:00–15:00.
 *
 * @return array{0: Ujian, 1: KelasKuliah, 2: list<User>}
 */
function ujianBerkas(int $jumlahMahasiswa = 2): array
{
    Storage::fake('local');
    [$kelas, $mahasiswa] = kelasUjian($jumlahMahasiswa);
    $ujian = Ujian::create([...isianUjian(['mode' => 'online_berkas', 'ruang_id' => null, 'status' => 'terbit']), 'kelas_id' => $kelas->id, 'jenis' => 'uts']);

    return [$ujian, $kelas, $mahasiswa];
}

/**
 * Ganti akun di tengah tes: sesi akun sebelumnya dibuang agar AuthenticateSession tidak mengeluarkan akun baru.
 */
function gantiAkun($test, User $user)
{
    app('session')->flush();
    app('auth')->forgetGuards();

    return $test->actingAs($user);
}

it('lets the lecturer manage question files only before the exam starts', function () {
    [$ujian, $kelas] = ujianBerkas();
    $dosen = $kelas->dosen->user;

    $this->travelTo('2025-10-06 12:00:00');
    $this->actingAs($dosen)->post(route('dosen.ujian.soal.unggah', $ujian), ['soal' => [UploadedFile::fake()->create('soal uts.pdf', 200, 'application/pdf')]])
        ->assertSessionHas('success', '1 berkas soal diunggah.');
    $this->actingAs($dosen)->post(route('dosen.ujian.soal.unggah', $ujian), ['soal' => [UploadedFile::fake()->create('lampiran.pdf', 10, 'application/pdf')]]);
    expect($ujian->fresh()->soal_berkas)->toHaveCount(2);
    Storage::disk('local')->assertExists($ujian->fresh()->soal_berkas[0]);

    $this->actingAs($dosen)->delete(route('dosen.ujian.soal.hapus', [$ujian, 1]))->assertSessionHas('success');
    expect($ujian->fresh()->soal_berkas)->toHaveCount(1);

    $this->travelTo('2025-10-06 13:00:00');
    $this->actingAs($dosen)->post(route('dosen.ujian.soal.unggah', $ujian), ['soal' => [UploadedFile::fake()->create('ralat.pdf', 10, 'application/pdf')]])
        ->assertSessionHas('error', 'Ujian sudah dimulai; soal tidak bisa diubah lagi.');

    $this->actingAs(createMateriKelasKuliah()->dosen->user)->get(route('dosen.ujian.show', $ujian))->assertForbidden();
});

it('keeps questions hidden from students until the exam starts', function () {
    [$ujian, $kelas, $mahasiswa] = ujianBerkas(1);
    $this->travelTo('2025-10-06 12:00:00');
    $this->actingAs($kelas->dosen->user)->post(route('dosen.ujian.soal.unggah', $ujian), ['soal' => [UploadedFile::fake()->create('soal.pdf', 20, 'application/pdf')]]);
    $url = route('berkas.ujian-soal', [$ujian, 0]);

    gantiAkun($this, $mahasiswa[0])->get(route('mahasiswa.ujian.show', $ujian))
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/UjianShow')->where('ujian.soal', [])->where('detikSampaiMulai', 3600));
    $this->actingAs($mahasiswa[0])->get($url)->assertForbidden();

    $this->travelTo('2025-10-06 13:00:00');
    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.ujian.show', $ujian))->assertInertia(fn ($page) => $page->has('ujian.soal', 1));
    $this->actingAs($mahasiswa[0])->get($url)->assertOk();

    // Bukan peserta atau jadwal masih draf: tidak bisa dibuka.
    gantiAkun($this, User::factory()->mahasiswa()->create())->get(route('mahasiswa.ujian.show', $ujian))->assertNotFound();
    $ujian->update(['status' => 'draf']);
    gantiAkun($this, $mahasiswa[0])->get(route('mahasiswa.ujian.show', $ujian))->assertNotFound();
});

it('accepts answers only during the exam and records attendance', function () {
    [$ujian, $kelas, $mahasiswa] = ujianBerkas(1);
    $id = $mahasiswa[0]->mahasiswaProfile->id;
    $kirim = fn (string $nama = 'jawaban.pdf') => $this->actingAs($mahasiswa[0])->post(route('mahasiswa.ujian.kumpulkan', $ujian), ['jawaban' => [UploadedFile::fake()->create($nama, 50, 'application/pdf')]]);

    $this->travelTo('2025-10-06 12:59:00');
    $kirim()->assertSessionHasErrors(['jawaban' => 'Ujian belum dimulai.']);

    $this->travelTo('2025-10-06 13:30:00');
    $kirim()->assertSessionHas('success');
    $pertama = $ujian->jawabans()->first()->berkas[0];

    $this->travelTo('2025-10-06 14:59:00');
    $kirim('revisi.pdf')->assertSessionHas('success');
    $jawaban = $ujian->jawabans()->first();
    expect($ujian->jawabans()->count())->toBe(1)->and($jawaban->dikumpulkan_at->format('H:i'))->toBe('14:59');
    Storage::disk('local')->assertMissing($pertama);

    $this->travelTo('2025-10-06 15:00:01');
    $kirim('telat.pdf')->assertSessionHasErrors(['jawaban' => 'Waktu ujian sudah habis. Jawaban tidak bisa dikumpulkan lagi.']);
    expect($ujian->jawabans()->first()->dikumpulkan_at->format('H:i'))->toBe('14:59');

    $uts = $ujian->pertemuan()->fresh();
    expect($uts->status)->toBe(Pertemuan::BERLANGSUNG)
        ->and($uts->presensiMahasiswas()->where('mahasiswa_id', $id)->value('status'))->toBe('hadir')
        ->and($uts->presensiMahasiswas()->where('mahasiswa_id', $id)->value('metode'))->toBe('ujian');
});

it('blocks students who do not meet the attendance requirement', function () {
    [$ujian, $kelas, $mahasiswa] = ujianBerkas(1);
    PengaturanAkademik::current()->update(['syarat_ujian_aktif' => true]);
    $admin = User::factory()->admin()->create();
    $p = Pertemuan::where('kelas_id', $kelas->id)->where('pertemuan_ke', 1)->first();
    $this->actingAs($admin)->post(route('admin.presensi.pertemuan.mulai', $p));
    $this->actingAs($admin)->post(route('admin.presensi.pertemuan.selesai', $p), ['topik' => 'x']); // tercatat alpa
    $this->actingAs($admin)->post(route('admin.ujian.soal.unggah', $ujian), ['soal' => [UploadedFile::fake()->create('soal.pdf', 20, 'application/pdf')]]);

    $this->travelTo('2025-10-06 13:30:00');
    gantiAkun($this, $mahasiswa[0])->get(route('mahasiswa.ujian.show', $ujian))->assertInertia(fn ($page) => $page->where('bolehIkut', false)->where('ujian.soal', []));
    $this->actingAs($mahasiswa[0])->get(route('berkas.ujian-soal', [$ujian, 0]))->assertForbidden();
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.ujian.kumpulkan', $ujian), ['jawaban' => [UploadedFile::fake()->create('j.pdf', 10, 'application/pdf')]])
        ->assertSessionHasErrors(['jawaban' => 'Anda belum memenuhi syarat kehadiran untuk mengikuti ujian ini.']);
});

it('lets the lecturer grade after the exam and release the scores', function () {
    [$ujian, $kelas, $mahasiswa] = ujianBerkas(2);
    $dosen = $kelas->dosen->user;
    $this->travelTo('2025-10-06 13:30:00');
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.ujian.kumpulkan', $ujian), ['jawaban' => [UploadedFile::fake()->create('j.pdf', 10, 'application/pdf')]]);
    $jawaban = $ujian->jawabans()->first();

    // Berkas jawaban: pemilik, dosen pengampu; mahasiswa lain tidak.
    $this->actingAs($mahasiswa[0])->get(route('berkas.ujian-jawaban', [$jawaban, 0]))->assertOk();
    gantiAkun($this, $mahasiswa[1])->get(route('berkas.ujian-jawaban', [$jawaban, 0]))->assertForbidden();

    $mhs0 = $mahasiswa[0]->mahasiswaProfile;
    gantiAkun($this, $dosen)->put(route('dosen.ujian.nilai', [$ujian, $mhs0]), ['nilai' => 80])->assertSessionHas('error', 'Nilai diisi setelah ujian selesai.');

    $this->travelTo('2025-10-06 15:30:00');
    $this->actingAs($dosen)->get(route('dosen.ujian.show', $ujian))
        ->assertInertia(fn ($page) => $page->component('Kelas/UjianKelas')->has('peserta', 2)->where('sudahSelesai', true));
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$ujian, $mhs0]), ['nilai' => 87.5, 'catatan_dosen' => 'Bagus'])->assertSessionHas('success');
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$ujian, $mhs0]), ['nilai' => 120])->assertSessionHasErrors('nilai');
    // Mode berkas: yang tidak mengumpulkan tidak bisa dinilai (tidak dibuatkan jawaban kosong).
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$ujian, $mahasiswa[1]->mahasiswaProfile]), ['nilai' => 0])
        ->assertSessionHas('error', 'Mahasiswa ini tidak mengumpulkan jawaban.');
    expect($ujian->jawabans()->count())->toBe(1)->and((float) $jawaban->fresh()->nilai)->toBe(87.5);

    gantiAkun($this, $mahasiswa[0])->get(route('mahasiswa.ujian.show', $ujian))->assertInertia(fn ($page) => $page->where('jawaban.nilai', null));
    gantiAkun($this, $dosen)->put(route('dosen.ujian.rilis-nilai', $ujian), ['nilai_dirilis' => true])->assertSessionHas('success');
    gantiAkun($this, $mahasiswa[0])->get(route('mahasiswa.ujian.show', $ujian))
        ->assertInertia(fn ($page) => $page->where('jawaban.nilai', '87.50')->where('jawaban.catatan_dosen', 'Bagus'));
});

/**
 * Ujian mode soal di sistem (terbit, 2025-10-06 13:00–15:00) beserta lembar soal berisi dua soal pilihan tunggal.
 *
 * @return array{0: Ujian, 1: KelasKuliah, 2: list<User>, 3: Quiz}
 */
function ujianSoal(int $jumlahMahasiswa = 2): array
{
    [$kelas, $mahasiswa] = kelasUjian($jumlahMahasiswa);
    $ujian = Ujian::create([...isianUjian(['mode' => 'online_soal', 'ruang_id' => null, 'status' => 'terbit']), 'kelas_id' => $kelas->id, 'jenis' => 'uts']);
    $quiz = Quiz::create([
        'nama_quiz' => 'UTS', 'tenggat_waktu' => $ujian->akhirAt(), 'uploaded_by' => $kelas->dosen->user_id,
        'kelas_id' => $kelas->id, 'ujian_id' => $ujian->id,
    ]);
    foreach (['Satu', 'Dua'] as $teks) {
        $quiz->questions()->create([
            'question_text' => $teks, 'question_type' => 'single_choice', 'points' => 10,
            'question_option' => [['text' => 'A', 'is_correct' => true], ['text' => 'B', 'is_correct' => false], ['text' => 'C', 'is_correct' => false]],
        ]);
    }

    return [$ujian, $kelas, $mahasiswa, $quiz];
}

it('creates the question sheet from the exam and keeps it out of the quiz list', function () {
    [$kelas] = kelasUjian(1);
    $ujian = Ujian::create([...isianUjian(['mode' => 'online_soal', 'ruang_id' => null, 'status' => 'terbit']), 'kelas_id' => $kelas->id, 'jenis' => 'uts']);
    $dosen = $kelas->dosen->user;

    $this->travelTo('2025-10-06 10:00:00');
    $this->actingAs($dosen)->post(route('dosen.ujian.lembar-soal', $ujian))->assertRedirect();
    $quiz = $ujian->fresh()->quiz;
    expect($quiz)->not->toBeNull()
        ->and($quiz->tenggat_waktu->format('Y-m-d H:i'))->toBe('2025-10-06 15:00');

    // Dibuat sekali saja; tidak muncul di daftar quiz kelas, dan tidak bisa diduplikasi/dihapus dari menu quiz.
    $this->actingAs($dosen)->post(route('dosen.ujian.lembar-soal', $ujian));
    expect(Quiz::count())->toBe(1)->and($kelas->quizzes()->count())->toBe(0);
    $this->actingAs($dosen)->delete(route('dosen.kelas-kuliah.quiz.destroy', [$kelas, $quiz]))->assertNotFound();

    $this->actingAs($dosen)->get(route('dosen.ujian.show', $ujian))
        ->assertInertia(fn ($page) => $page->where('lembarSoal.id', $quiz->id)->where('lembarSoal.jumlah_soal', 0));

    // Jam ujian digeser admin: tenggat lembar soal ikut.
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.ujian.update', $ujian), isianUjian(['mode' => 'online_soal', 'ruang_id' => null, 'jam_akhir' => '16:00', 'status' => 'terbit']))
        ->assertSessionHasNoErrors();
    expect($quiz->fresh()->tenggat_waktu->format('H:i'))->toBe('16:00');
});

it('locks the questions once the exam starts', function () {
    [$ujian, $kelas, , $quiz] = ujianSoal();
    $dosen = $kelas->dosen->user;
    $soal = $quiz->questions()->first();
    $baru = ['questions' => [['question_text' => 'Tiga', 'question_type' => 'essay', 'points' => 5]]];

    $this->travelTo('2025-10-06 12:00:00');
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.quiz.questions.store', [$kelas, $quiz]), $baru)->assertSessionHasNoErrors();
    expect($quiz->questions()->count())->toBe(3);

    $this->travelTo('2025-10-06 13:00:00');
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.quiz.questions.store', [$kelas, $quiz]), $baru)
        ->assertSessionHas('question_error', 'Ujian sudah dimulai; soal tidak bisa diubah lagi.');
    $this->actingAs($dosen)->delete(route('dosen.kelas-kuliah.quiz.questions.destroy', [$kelas, $quiz, $soal]))
        ->assertSessionHas('question_error');
    expect($quiz->questions()->count())->toBe(3);
});

it('lets students take the exam once, only during the exam window', function () {
    [$ujian, $kelas, $mahasiswa, $quiz] = ujianSoal();
    $soal = $quiz->questions()->get();

    $this->travelTo('2025-10-06 12:59:00');
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.quiz.start', $quiz))
        ->assertRedirect(route('mahasiswa.ujian.show', $ujian))->assertSessionHas('error', 'Ujian belum dimulai.');
    // Soal tidak terlihat sebelum mulai.
    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.quiz.show', $quiz))->assertInertia(fn ($page) => $page->has('quiz.questions', 0));

    $this->travelTo('2025-10-06 13:05:00');
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.quiz.start', $quiz))->assertRedirect(route('mahasiswa.quiz.show', $quiz));
    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.quiz.show', $quiz))
        ->assertInertia(fn ($page) => $page->has('quiz.questions', 2)->where('ujian.id', $ujian->id));

    // Mengerjakan dicatat hadir di pertemuan UTS.
    $uts = $ujian->pertemuan();
    expect($uts->fresh()->status)->toBe(Pertemuan::BERLANGSUNG)
        ->and(PresensiMahasiswa::where('pertemuan_id', $uts->id)->where('mahasiswa_id', $mahasiswa[0]->mahasiswaProfile->id)->value('status'))->toBe(PresensiMahasiswa::HADIR);

    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$soal[0]->id => 'A', $soal[1]->id => 'B']])->assertSessionHas('success');
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.quiz.start', $quiz))->assertSessionHas('error', 'Quiz sudah pernah dikerjakan.');
    expect(QuizAttempt::where('quiz_id', $quiz->id)->count())->toBe(1);

    // Lewat jam selesai: mahasiswa lain tidak bisa mulai lagi.
    $this->travelTo('2025-10-06 15:01:00');
    gantiAkun($this, $mahasiswa[1])->post(route('mahasiswa.quiz.start', $quiz))->assertSessionHas('error', 'Waktu ujian sudah habis.');

    // Ujian yang sudah dikerjakan tidak bisa dihapus atau diganti modenya.
    $admin = User::factory()->admin()->create();
    gantiAkun($this, $admin)->delete(route('admin.ujian.destroy', $ujian))->assertSessionHas('error');
    $this->put(route('admin.ujian.update', $ujian), isianUjian(['mode' => 'online_berkas', 'ruang_id' => null, 'status' => 'terbit']))->assertSessionHasErrors('mode');
    expect(Ujian::find($ujian->id))->not->toBeNull();
});

it('hides the exam score until the lecturer releases it', function () {
    [$ujian, $kelas, $mahasiswa, $quiz] = ujianSoal(1);
    $soal = $quiz->questions()->get();

    $this->travelTo('2025-10-06 13:05:00');
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.quiz.start', $quiz));
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$soal[0]->id => 'A', $soal[1]->id => 'B']]);
    expect((float) QuizAttempt::first()->score)->toBe(10.0);

    // Rilis ditolak selama ujian masih berjalan.
    gantiAkun($this, $kelas->dosen->user)->put(route('dosen.ujian.rilis-nilai', $ujian), ['nilai_dirilis' => true])
        ->assertSessionHas('error', 'Nilai baru bisa dirilis setelah ujian selesai.');
    expect($ujian->fresh()->nilai_dirilis)->toBeFalse();
    gantiAkun($this, $mahasiswa[0]);

    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.quiz.show', $quiz))
        ->assertInertia(fn ($page) => $page->where('attempt.score', null)->where('ujian.nilai_dirilis', false));
    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.ujian.show', $ujian))
        ->assertInertia(fn ($page) => $page->where('pengerjaan.selesai', true)->where('pengerjaan.skor', null));

    $this->travelTo('2025-10-06 15:30:00');
    gantiAkun($this, $kelas->dosen->user)->get(route('dosen.ujian.show', $ujian))
        ->assertInertia(fn ($page) => $page->where('peserta.0.pengerjaan.skor', '10.00')->where('peserta.0.pengerjaan.nilai', 50)->where('lembarSoal.total_poin', 20));
    $this->put(route('dosen.ujian.rilis-nilai', $ujian), ['nilai_dirilis' => true])->assertSessionHas('success');

    gantiAkun($this, $mahasiswa[0])->get(route('mahasiswa.ujian.show', $ujian))->assertInertia(fn ($page) => $page->where('pengerjaan.skor', '10.00')->where('pengerjaan.nilai', 50));
    $this->get(route('mahasiswa.quiz.show', $quiz))->assertInertia(fn ($page) => $page->where('attempt.score', '10.00'));
});

it('keeps draft exam question sheets closed to students', function () {
    [$ujian, , $mahasiswa, $quiz] = ujianSoal(1);
    $ujian->update(['status' => 'draf']);

    $this->travelTo('2025-10-06 13:05:00');
    $this->actingAs($mahasiswa[0])->get(route('mahasiswa.quiz.show', $quiz))->assertNotFound();
    $this->actingAs($mahasiswa[0])->post(route('mahasiswa.quiz.start', $quiz))->assertSessionHas('error', 'Ujian belum diterbitkan.');
});

it('lets the lecturer grade a face-to-face exam per student', function () {
    [$kelas, $mahasiswa] = kelasUjian(2);
    $ujian = Ujian::create([...isianUjian(['status' => 'terbit']), 'kelas_id' => $kelas->id, 'jenis' => 'uts']);
    $dosen = $kelas->dosen->user;
    $mhs = $mahasiswa[0]->mahasiswaProfile;

    $this->travelTo('2025-10-06 14:00:00');
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$ujian, $mhs]), ['nilai' => 75])->assertSessionHas('error', 'Nilai diisi setelah ujian selesai.');

    $this->travelTo('2025-10-06 15:30:00');
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$ujian, $mhs]), ['nilai' => 75, 'catatan_dosen' => 'Cukup'])->assertSessionHas('success');
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$ujian, $mhs]), ['nilai' => 78])->assertSessionHas('success');
    expect($ujian->jawabans()->count())->toBe(1)
        ->and($ujian->jawabans()->first()->only(['nilai', 'berkas', 'dikumpulkan_at']))->toBe(['nilai' => '78.00', 'berkas' => null, 'dikumpulkan_at' => null]);

    // Bukan peserta kelas → 404; lembar soal tidak lewat jalur ini.
    $luar = User::factory()->mahasiswa()->create()->mahasiswaProfile;
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$ujian, $luar]), ['nilai' => 90])->assertNotFound();

    $this->actingAs($dosen)->get(route('dosen.ujian.show', $ujian))->assertInertia(fn ($page) => $page->where('peserta', fn ($peserta) => collect($peserta)->firstWhere('mahasiswa_id', $mhs->id)['jawaban']['nilai'] === '78.00'));
    $this->put(route('dosen.ujian.rilis-nilai', $ujian), ['nilai_dirilis' => true])->assertSessionHas('success');
    gantiAkun($this, $mahasiswa[0])->get(route('mahasiswa.ujian.show', $ujian))->assertInertia(fn ($page) => $page->where('jawaban.nilai', '78.00'));
});

it('records attendance even when the exam meeting was opened without participant rows', function () {
    // Mensimulasikan request lain yang sudah membuka pertemuan tetapi belum selesai mengisi peserta.
    [$ujian, , $mahasiswa] = ujianSoal(2);
    $uts = $ujian->pertemuan();
    $uts->update(['status' => Pertemuan::BERLANGSUNG]);
    $mhs = $mahasiswa[0]->mahasiswaProfile->id;

    $ujian->catatHadir($mhs);
    $uts->siapkanPeserta();

    expect(PresensiMahasiswa::where('pertemuan_id', $uts->id)->where('mahasiswa_id', $mhs)->value('status'))->toBe(PresensiMahasiswa::HADIR)
        ->and(PresensiMahasiswa::where('pertemuan_id', $uts->id)->count())->toBe(2);
});
