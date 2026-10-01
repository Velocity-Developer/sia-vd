<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Materi;
use App\Models\PengajuanSusulan;
use App\Models\Pertemuan;
use App\Models\Quiz;
use App\Models\Tugas;
use App\Models\Ujian;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * Fitur per klien yang bawaannya nyala (materi, tugas, quiz, ujian_online, presensi_qr, pindah_kelas, ujian_susulan)
 * dalam keadaan mati. Tes lama memakai keadaan nyala (lihat tests/TestCase.php).
 */

beforeEach(fn () => Storage::fake('local'));

function matikanFitur(string ...$fitur): void
{
    foreach ($fitur as $nama) {
        config(["client.fitur.{$nama}.default" => false]);
    }
}

/**
 * Kelas dengan satu mahasiswa ber-KRS dan masing-masing satu materi, tugas, dan quiz kelas.
 *
 * @return array{0: KelasKuliah, 1: User, 2: Materi, 3: Tugas, 4: Quiz}
 */
function kelasBerkonten(): array
{
    $kelas = createMateriKelasKuliah();
    $mhs = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    $pengunggah = $kelas->dosen->user_id;
    $materi = Materi::create(['kelas_id' => $kelas->id, 'uploaded_by' => $pengunggah, 'judul_materi' => 'Materi 1', 'pertemuan_ke' => 1, 'jenis' => 'Materi', 'file' => []]);
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $pengunggah, 'judul_tugas' => 'Tugas 1']);
    $quiz = Quiz::create(['kelas_id' => $kelas->id, 'uploaded_by' => $pengunggah, 'nama_quiz' => 'Quiz 1', 'tenggat_waktu' => now()->addWeek()]);

    return [$kelas, $mhs, $materi, $tugas, $quiz];
}

it('hides materi, tugas, and quiz everywhere while the features are off', function () {
    matikanFitur('materi', 'tugas', 'quiz');
    [$kelas, $mhs, $materi, $tugas, $quiz] = kelasBerkonten();
    $admin = User::factory()->admin()->create();
    $dosen = $kelas->dosen->user;

    foreach (['admin' => $admin, 'dosen' => $dosen] as $peran => $user) {
        foreach (['materi', 'tugas', 'quiz'] as $menu) {
            $this->actingAs($user)->get(route("{$peran}.{$menu}.index"))->assertNotFound();
            $this->actingAs($user)->get(route("{$peran}.kelas-kuliah.{$menu}.create", $kelas))->assertNotFound();
        }
        $this->actingAs($user)->get(route("{$peran}.kelas-kuliah.materi.edit", [$kelas, $materi]))->assertNotFound();
        $this->actingAs($user)->get(route("{$peran}.kelas-kuliah.tugas.show", [$kelas, $tugas]))->assertNotFound();
        $this->actingAs($user)->get(route("{$peran}.kelas-kuliah.quiz.show", [$kelas, $quiz]))->assertNotFound();
        $this->actingAs($user)->get(route("{$peran}.kelas-kuliah.show", $kelas))->assertInertia(fn ($page) => $page
            ->where('kelasKuliah.materis', [])->where('kelasKuliah.tugas', [])->where('kelasKuliah.quizzes', []));
    }

    $this->actingAs($mhs)->get(route('mahasiswa.materi.show', $materi))->assertNotFound();
    $this->actingAs($mhs)->get(route('mahasiswa.tugas.show', $tugas))->assertNotFound();
    $this->actingAs($mhs)->get(route('mahasiswa.quiz.show', $quiz))->assertNotFound();
    $this->actingAs($mhs)->get(route('mahasiswa.jadwal-kuliah.show', $kelas))->assertInertia(fn ($page) => $page
        ->where('kelasKuliah.materis', [])->where('kelasKuliah.tugas', [])->where('kelasKuliah.quizzes', []));
    $this->actingAs($mhs)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page->where('tugasMendatang', null));
    $this->actingAs($dosen)->get(route('dosen.dashboard'))->assertInertia(fn ($page) => $page->where('perluDinilai', null));

    expect($admin->permissionKeys())->not->toContain('admin.materi')->not->toContain('admin.tugas')->not->toContain('admin.quiz')
        ->and($dosen->permissionKeys())->not->toContain('dosen.materi')->not->toContain('dosen.quiz');

    config(['client.fitur.materi.default' => true]);
    $this->actingAs($admin)->get(route('admin.materi.index'))->assertOk();
    $this->actingAs($mhs)->get(route('mahasiswa.materi.show', $materi))->assertOk();
});

it('keeps the online exam answer sheet reachable when only quiz is off', function () {
    matikanFitur('quiz');
    [$kelas, , , , $quiz] = kelasBerkonten();
    $admin = User::factory()->admin()->create();
    $ujian = Ujian::create(['kelas_id' => $kelas->id, 'jenis' => 'uts', 'mode' => 'online_soal', 'tanggal' => '2025-10-06', 'jam_mulai' => '13:00', 'jam_akhir' => '15:00', 'status' => 'draf']);
    $lembar = Quiz::create(['kelas_id' => $kelas->id, 'uploaded_by' => $admin->id, 'nama_quiz' => 'Lembar UTS', 'tenggat_waktu' => $ujian->akhirAt(), 'ujian_id' => $ujian->id]);

    $this->actingAs($admin)->get(route('admin.kelas-kuliah.quiz.show', [$kelas, $quiz]))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.kelas-kuliah.quiz.show', [$kelas, $lembar]))->assertOk();

    matikanFitur('ujian_online');
    $this->actingAs($admin)->get(route('admin.kelas-kuliah.quiz.show', [$kelas, $lembar]))->assertNotFound();
});

it('allows only face-to-face exams while ujian_online is off', function () {
    matikanFitur('ujian_online');
    [$kelas, $mhs] = kelasBerkonten();
    $admin = User::factory()->admin()->create();
    $isian = ['kelas_id' => $kelas->id, 'jenis' => 'uts', 'tanggal' => '2025-10-06', 'jam_mulai' => '13:00', 'jam_akhir' => '15:00', 'status' => 'draf'];

    $this->actingAs($admin)->post(route('admin.ujian.store'), [...$isian, 'mode' => Ujian::ONLINE_BERKAS])->assertSessionHasErrors('mode');
    $this->actingAs($admin)->post(route('admin.ujian.store'), [...$isian, 'mode' => Ujian::ONLINE_SOAL])->assertSessionHasErrors('mode');
    $this->actingAs($admin)->post(route('admin.ujian.store'), [...$isian, 'mode' => Ujian::TATAP_MUKA])->assertSessionDoesntHaveErrors('mode');

    $ujian = Ujian::create([...$isian, 'jenis' => 'uas', 'mode' => Ujian::ONLINE_BERKAS, 'status' => 'terbit']);
    $this->actingAs($admin)->post(route('admin.ujian.lembar-soal', $ujian))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.ujian.soal.unggah', $ujian))->assertNotFound();
    $this->actingAs($mhs)->post(route('mahasiswa.ujian.kumpulkan', $ujian))->assertNotFound();
});

it('drops ujian susulan and stops it from holding grade finalization', function () {
    [$kelas, $mhs] = kelasBerkonten();
    $admin = User::factory()->admin()->create();
    $uas = Ujian::create(['kelas_id' => $kelas->id, 'jenis' => 'uas', 'mode' => Ujian::TATAP_MUKA, 'tanggal' => '2025-12-15', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'status' => 'terbit']);
    PengajuanSusulan::create(['ujian_id' => $uas->id, 'mahasiswa_id' => $mhs->mahasiswaProfile->id, 'alasan' => 'Sakit', 'lampiran' => [], 'status' => PengajuanSusulan::DISETUJUI]);
    $this->travelTo('2025-12-16 09:00:00');
    $dosen = $kelas->dosen->user;

    // Nyala: UAS susulan yang disetujui menahan finalisasi.
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('error');

    matikanFitur('ujian_susulan');
    $this->actingAs($admin)->get(route('admin.ujian-susulan.index'))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.ujian.susulan-massal'))->assertNotFound();
    $this->actingAs($mhs)->post(route('mahasiswa.ujian.susulan', $uas), ['alasan' => 'Sakit'])->assertNotFound();
    $this->actingAs($admin)->post(route('admin.ujian.store'), [
        'kelas_id' => $kelas->id, 'jenis' => 'uas_susulan', 'mode' => Ujian::TATAP_MUKA, 'tanggal' => '2025-12-20', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'status' => 'draf',
    ])->assertSessionHasErrors('jenis');
    $this->actingAs($mhs)->get(route('mahasiswa.ujian.show', $uas))->assertInertia(fn ($page) => $page->where('susulan', null));
    $this->actingAs($mhs)->get(route('mahasiswa.ujian'))->assertInertia(fn ($page) => $page->where('ujians.0.terdaftar_susulan', false));

    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('success');
});

it('records attendance manually only while presensi_qr is off', function () {
    matikanFitur('presensi_qr');
    [$kelas, $mhs] = kelasBerkonten();
    $this->travelTo('2025-10-06 08:30:00');
    $pertemuan = Pertemuan::create([
        'kelas_id' => $kelas->id, 'pertemuan_ke' => 1, 'tanggal' => '2025-10-06', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00',
        'jenis' => Pertemuan::KULIAH, 'status' => Pertemuan::BERLANGSUNG, 'dosen_id' => $kelas->dosen_id, 'mandiri_sampai' => now()->addMinutes(15),
    ]);
    $dosen = $kelas->dosen->user;

    expect($pertemuan->mandiriTerbuka())->toBeFalse();
    $this->actingAs($dosen)->post(route('dosen.presensi.pertemuan.mandiri.buka', $pertemuan))->assertNotFound();
    $this->actingAs($dosen)->get(route('dosen.presensi.pertemuan.kode', $pertemuan))->assertNotFound();
    $this->actingAs($mhs)->get(route('mahasiswa.presensi.masuk', $pertemuan))->assertNotFound();
    $this->actingAs($mhs)->post(route('mahasiswa.presensi.check-in'), ['pertemuan_id' => $pertemuan->id, 'kode' => '123456'])->assertNotFound();
    $this->actingAs($mhs)->get(route('mahasiswa.presensi'))->assertInertia(fn ($page) => $page->where('terbuka', []));
    $this->actingAs($dosen)->get(route('dosen.presensi.pertemuan.show', $pertemuan))->assertInertia(fn ($page) => $page->where('mandiriTerbuka', false));
});

it('closes pindah kelas for admin and students while the feature is off', function () {
    matikanFitur('pindah_kelas');
    [, $mhs] = kelasBerkonten();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.pindah-kelas.index'))->assertNotFound();
    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.pindah-kelas'), ['is_active' => true])->assertNotFound();
    $this->actingAs($mhs)->get(route('mahasiswa.pindah-kelas'))->assertNotFound();
    $this->actingAs($mhs)->post(route('mahasiswa.pindah-kelas.store'))->assertNotFound();

    expect($admin->permissionKeys())->not->toContain('admin.pindah-kelas')
        ->and($mhs->permissionKeys())->not->toContain('mahasiswa.pindah-kelas');
});
