<?php

use App\Models\Jadwal;
use App\Models\Materi;
use App\Models\Quiz;
use App\Models\Ruang;
use App\Models\TahunAkademik;
use App\Models\Tugas;
use App\Models\User;

function ruangMenu(): Ruang
{
    return Ruang::create(['kode_ruang' => 'R-'.random_int(100, 999), 'nama_ruang' => 'Ruang Menu', 'kapasitas' => 30]);
}

/**
 * Isian minimal tiap menu (tanpa kelas_kuliah_id).
 *
 * @return array<string, mixed>
 */
function isianMenu(string $menu): array
{
    return match ($menu) {
        'jadwal' => ['hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => ruangMenu()->id],
        'materi' => ['judul_materi' => 'Materi dari menu', 'pertemuan_ke' => 1, 'jenis' => 'Materi'],
        'tugas' => ['judul_tugas' => 'Tugas dari menu'],
        'quiz' => ['nama_quiz' => 'Quiz dari menu'],
    };
}

function modelMenu(string $menu): string
{
    return ['jadwal' => Jadwal::class, 'materi' => Materi::class, 'tugas' => Tugas::class, 'quiz' => Quiz::class][$menu];
}

it('shows the kelas kuliah field with active, non-TA classes on the menu create page', function (string $menu, string $komponen) {
    $kelas = createMateriKelasKuliah();
    $kelasTa = createMateriKelasKuliah($kelas->tahunAkademik);
    $kelasTa->mataKuliah->update(['tugas_akhir' => true]);
    $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Genap', 'tanggal_mulai' => '2025-02-01', 'tanggal_akhir' => '2025-07-31', 'tanggal_krs_awal' => '2025-02-01', 'tanggal_krs_akhir' => '2025-02-14', 'status' => false]);
    createMateriKelasKuliah($lalu);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route("admin.{$menu}.create"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($komponen)
            ->where('kelasKuliah', null)
            ->where('dariMenu', true)
            ->has('kelasOptions', 1)
            ->where('kelasOptions.0.id', $kelas->id));
})->with([
    ['jadwal', 'Kelas/JadwalForm'],
    ['materi', 'Kelas/MateriForm'],
    ['tugas', 'Kelas/TugasForm'],
    ['quiz', 'Kelas/QuizForm'],
]);

it('lets the admin add content from the menu to the chosen class', function (string $menu) {
    $kelas = createMateriKelasKuliah();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route("admin.{$menu}.store"), ['kelas_kuliah_id' => $kelas->id, 'dari' => 'menu', ...isianMenu($menu)])
        ->assertSessionHasNoErrors();

    $dibuat = modelMenu($menu)::where('kelas_id', $kelas->id)->sole();
    // Quiz baru langsung dibuka agar soalnya bisa diisi; lainnya kembali ke daftar menu.
    $response->assertRedirect($menu === 'quiz' ? route('admin.kelas-kuliah.quiz.show', [$kelas, $dibuat]) : route("admin.{$menu}.index"));
})->with(['jadwal', 'materi', 'tugas', 'quiz']);

it('only lets the dosen pick classes they teach', function (string $menu) {
    $kelasSendiri = createMateriKelasKuliah();
    $kelasLain = createMateriKelasKuliah($kelasSendiri->tahunAkademik);
    $dosen = $kelasSendiri->dosen->user;

    $this->actingAs($dosen)->get(route("dosen.{$menu}.create"))
        ->assertInertia(fn ($page) => $page->has('kelasOptions', 1)->where('kelasOptions.0.id', $kelasSendiri->id));

    $this->actingAs($dosen)
        ->post(route("dosen.{$menu}.store"), ['kelas_kuliah_id' => $kelasLain->id, ...isianMenu($menu)])
        ->assertSessionHasErrors('kelas_kuliah_id');

    $this->actingAs($dosen)
        ->post(route("dosen.{$menu}.store"), [...isianMenu($menu)])
        ->assertSessionHasErrors('kelas_kuliah_id');

    $this->actingAs($dosen)
        ->post(route("dosen.{$menu}.store"), ['kelas_kuliah_id' => $kelasSendiri->id, ...isianMenu($menu)])
        ->assertSessionHasNoErrors();

    expect(modelMenu($menu)::where('kelas_id', $kelasLain->id)->exists())->toBeFalse()
        ->and(modelMenu($menu)::where('kelas_id', $kelasSendiri->id)->count())->toBe(1);
})->with(['jadwal', 'materi', 'tugas', 'quiz']);

it('rejects a class from an inactive tahun akademik on the menu form', function () {
    $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Genap', 'tanggal_mulai' => '2025-02-01', 'tanggal_akhir' => '2025-07-31', 'tanggal_krs_awal' => '2025-02-01', 'tanggal_krs_akhir' => '2025-02-14', 'status' => false]);
    $kelasLalu = createMateriKelasKuliah($lalu);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tugas.store'), ['kelas_kuliah_id' => $kelasLalu->id, ...isianMenu('tugas')])
        ->assertSessionHasErrors('kelas_kuliah_id');
});

it('still checks schedule conflicts when adding a jadwal from the menu', function () {
    $kelasA = createMateriKelasKuliah();
    $kelasB = createMateriKelasKuliah($kelasA->tahunAkademik);
    $isian = isianMenu('jadwal');
    Jadwal::create(['kelas_id' => $kelasA->id, ...$isian]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.jadwal.store'), ['kelas_kuliah_id' => $kelasB->id, ...$isian])
        ->assertSessionHasErrors('ruang_id');
});

it('lets the dosen edit and delete jadwal of their own class in the active tahun akademik', function () {
    $kelas = createMateriKelasKuliah();
    $kelasLain = createMateriKelasKuliah($kelas->tahunAkademik);
    $dosen = $kelas->dosen->user;
    $isian = isianMenu('jadwal');
    $jadwal = Jadwal::create(['kelas_id' => $kelas->id, ...$isian]);
    $jadwalLain = Jadwal::create(['kelas_id' => $kelasLain->id, ...$isian, 'ruang_id' => ruangMenu()->id]);

    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.jadwal.edit', [$kelas, $jadwal, 'dari' => 'menu']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Kelas/JadwalForm')->where('peran', 'dosen')->where('dariMenu', true));

    $this->actingAs($dosen)
        ->put(route('dosen.kelas-kuliah.jadwal.update', [$kelas, $jadwal]), [...$isian, 'hari' => 'Rabu', 'dari' => 'menu'])
        ->assertRedirect(route('dosen.jadwal.index'));
    expect($jadwal->fresh()->hari)->toBe('Rabu');

    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.jadwal.edit', [$kelasLain, $jadwalLain]))->assertForbidden();
    $this->actingAs($dosen)->delete(route('dosen.kelas-kuliah.jadwal.destroy', [$kelasLain, $jadwalLain]))->assertForbidden();

    $this->actingAs($dosen)
        ->from(route('dosen.jadwal.index', ['hari' => 'x']))
        ->delete(route('dosen.kelas-kuliah.jadwal.destroy', [$kelas, $jadwal]).'?dari=menu')
        ->assertRedirect(route('dosen.jadwal.index', ['hari' => 'x']));
    expect(Jadwal::whereKey($jadwal->id)->exists())->toBeFalse()
        ->and(Jadwal::whereKey($jadwalLain->id)->exists())->toBeTrue();
});

it('locks jadwal changes for the dosen once the tahun akademik is inactive', function () {
    $kelas = createMateriKelasKuliah();
    $jadwal = Jadwal::create(['kelas_id' => $kelas->id, ...isianMenu('jadwal')]);
    $kelas->tahunAkademik->update(['status' => false]);

    $this->actingAs($kelas->dosen->user)
        ->delete(route('dosen.kelas-kuliah.jadwal.destroy', [$kelas, $jadwal]))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.kelas-kuliah.jadwal.destroy', [$kelas, $jadwal]))
        ->assertRedirect();
    expect(Jadwal::whereKey($jadwal->id)->exists())->toBeFalse();
});

it('returns to the menu after editing or deleting materi, tugas, and quiz from the menu', function (string $menu, string $kunci) {
    $kelas = createMateriKelasKuliah();
    $admin = User::factory()->admin()->create();
    $item = modelMenu($menu)::create(['kelas_id' => $kelas->id, 'uploaded_by' => $admin->id, ...isianMenu($menu)]);

    $this->actingAs($admin)->get(route("admin.kelas-kuliah.{$menu}.edit", [$kelas, $item, 'dari' => 'menu']))
        ->assertInertia(fn ($page) => $page->where('dariMenu', true));

    $this->actingAs($admin)
        ->put(route("admin.kelas-kuliah.{$menu}.update", [$kelas, $item]), [...isianMenu($menu), $kunci => 'Judul baru', 'dari' => 'menu'])
        ->assertRedirect(route("admin.{$menu}.index"));
    expect($item->fresh()->{$kunci})->toBe('Judul baru');

    // Tanpa penanda menu tetap kembali ke halaman kelas seperti sebelumnya.
    $this->actingAs($admin)
        ->put(route("admin.kelas-kuliah.{$menu}.update", [$kelas, $item]), [...isianMenu($menu)])
        ->assertRedirect(route('admin.kelas-kuliah.show', $kelas));

    $this->actingAs($admin)
        ->from(route("admin.{$menu}.index"))
        ->delete(route("admin.kelas-kuliah.{$menu}.destroy", [$kelas, $item]).'?dari=menu')
        ->assertRedirect(route("admin.{$menu}.index"));
    expect(modelMenu($menu)::whereKey($item->id)->exists())->toBeFalse();
})->with([
    ['materi', 'judul_materi'],
    ['tugas', 'judul_tugas'],
    ['quiz', 'nama_quiz'],
]);
