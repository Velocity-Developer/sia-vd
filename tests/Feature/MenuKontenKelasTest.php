<?php

use App\Models\Jadwal;
use App\Models\Materi;
use App\Models\Quiz;
use App\Models\Ruang;
use App\Models\Tugas;
use App\Models\User;

/**
 * Dua kelas dengan dosen berbeda pada tahun akademik aktif, masing-masing berisi jadwal, materi, tugas, quiz.
 */
function duaKelasBerkonten(): array
{
    $kelasA = createMateriKelasKuliah();
    $kelasB = createMateriKelasKuliah($kelasA->tahunAkademik);
    $ruang = Ruang::create(['kode_ruang' => 'R-501', 'nama_ruang' => 'Ruang 501', 'kapasitas' => 30]);

    foreach ([[$kelasA, 'Senin', 'Pengantar'], [$kelasB, 'Selasa', 'Lanjutan']] as [$kelas, $hari, $judul]) {
        Jadwal::create(['kelas_id' => $kelas->id, 'hari' => $hari, 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $ruang->id]);
        Materi::create(['kelas_id' => $kelas->id, 'judul_materi' => "Materi {$judul}", 'pertemuan_ke' => 1, 'jenis' => 'Materi', 'uploaded_by' => $kelas->dosen->user_id]);
        Materi::create(['kelas_id' => $kelas->id, 'judul_materi' => "Pengumuman {$judul}", 'pertemuan_ke' => 1, 'jenis' => 'Pengumuman', 'uploaded_by' => $kelas->dosen->user_id]);
        Tugas::create(['kelas_id' => $kelas->id, 'judul_tugas' => "Tugas {$judul}", 'uploaded_by' => $kelas->dosen->user_id]);
        Quiz::create(['kelas_id' => $kelas->id, 'nama_quiz' => "Quiz {$judul}", 'uploaded_by' => $kelas->dosen->user_id]);
    }

    return [$kelasA, $kelasB];
}

it('shows every class on the admin menus', function (string $menu, string $komponen, string $prop, int $jumlah) {
    duaKelasBerkonten();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route("admin.{$menu}.index"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($komponen)->where('peran', 'admin')->where("{$prop}.total", $jumlah));
})->with([
    ['jadwal', 'Kelas/JadwalIndex', 'jadwals', 2],
    ['materi', 'Kelas/MateriIndex', 'materis', 4],
    ['tugas', 'Kelas/TugasIndex', 'tugas', 2],
    ['quiz', 'Kelas/QuizIndex', 'quizzes', 2],
]);

it('limits the dosen menus to classes they teach', function (string $menu, string $prop, int $jumlah) {
    [$kelasA, $kelasB] = duaKelasBerkonten();

    $this->actingAs($kelasA->dosen->user)
        ->get(route("dosen.{$menu}.index"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('peran', 'dosen')->where("{$prop}.total", $jumlah)->where('dosenOptions', []));

    // Memaksa filter dosen lain lewat URL tidak membuka data kelas dosen tersebut.
    $this->actingAs($kelasA->dosen->user)
        ->get(route("dosen.{$menu}.index", ['dosen_id' => $kelasB->dosen_id]))
        ->assertInertia(fn ($page) => $page->where("{$prop}.total", $jumlah));
})->with([
    ['jadwal', 'jadwals', 1],
    ['materi', 'materis', 2],
    ['tugas', 'tugas', 1],
    ['quiz', 'quizzes', 1],
]);

it('filters the list by kelas, mata kuliah, dosen, and search', function () {
    [$kelasA] = duaKelasBerkonten();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.materi.index', ['kelas_id' => $kelasA->id]))
        ->assertInertia(fn ($page) => $page->where('materis.total', 2));

    $this->actingAs($admin)->get(route('admin.materi.index', ['mata_kuliah_id' => $kelasA->matkul_id]))
        ->assertInertia(fn ($page) => $page->where('materis.total', 2));

    $this->actingAs($admin)->get(route('admin.materi.index', ['dosen_id' => $kelasA->dosen_id]))
        ->assertInertia(fn ($page) => $page->where('materis.total', 2));

    $this->actingAs($admin)->get(route('admin.materi.index', ['prodi_id' => $kelasA->mataKuliah->prodi_id]))
        ->assertInertia(fn ($page) => $page->where('materis.total', 2));

    // "Pengantar" ada di judul materi dan pengumuman kelas A.
    $this->actingAs($admin)->get(route('admin.materi.index', ['search' => 'Pengantar']))
        ->assertInertia(fn ($page) => $page->where('materis.total', 2));

    $this->actingAs($admin)->get(route('admin.materi.index', ['search' => 'Pengumuman Pengantar']))
        ->assertInertia(fn ($page) => $page->where('materis.total', 1));

    $this->actingAs($admin)->get(route('admin.materi.index', ['jenis' => 'Pengumuman']))
        ->assertInertia(fn ($page) => $page->where('materis.total', 2)->where('jenis', 'Pengumuman'));

    $this->actingAs($admin)->get(route('admin.jadwal.index', ['search' => 'Senin']))
        ->assertInertia(fn ($page) => $page->where('jadwals.total', 1));

    $this->actingAs($admin)->get(route('admin.quiz.index', ['search' => 'Lanjutan']))
        ->assertInertia(fn ($page) => $page->where('quizzes.total', 1));
});

it('defaults to the active tahun akademik and can show every year', function () {
    [$kelasA] = duaKelasBerkonten();
    $lama = tahunAkademikAktif(false);
    $kelasLama = createMateriKelasKuliah($lama);
    Tugas::create(['kelas_id' => $kelasLama->id, 'judul_tugas' => 'Tugas Lama', 'uploaded_by' => $kelasLama->dosen->user_id]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.tugas.index'))
        ->assertInertia(fn ($page) => $page->where('filter.tahun_akademik_id', $kelasA->tahun_akademik_id)->where('tugas.total', 2));

    $this->actingAs($admin)->get(route('admin.tugas.index', ['tahun_akademik_id' => '']))
        ->assertInertia(fn ($page) => $page->where('filter.tahun_akademik_id', null)->where('tugas.total', 3));

    $this->actingAs($admin)->get(route('admin.tugas.index', ['tahun_akademik_id' => $lama->id]))
        ->assertInertia(fn ($page) => $page->where('tugas.total', 1));
});

it('keeps the menus behind their own permission', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();
    $dosen = User::factory()->dosen()->create();

    foreach (['jadwal', 'materi', 'tugas', 'quiz'] as $menu) {
        $this->actingAs($mahasiswa)->get(route("admin.{$menu}.index"))->assertForbidden();
        $this->actingAs($mahasiswa)->get(route("dosen.{$menu}.index"))->assertForbidden();
        $this->actingAs($dosen)->get(route("admin.{$menu}.index"))->assertForbidden();
        $this->actingAs($dosen)->get(route("dosen.{$menu}.index"))->assertOk();
    }
});
