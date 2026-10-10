<?php

use App\Models\DosenProfile;
use App\Models\Role;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use App\Models\User;

function taAktifDashboard(): TahunAkademik
{
    return TahunAkademik::create(['tahun' => '2025/2026', 'semester' => 'Ganjil', 'tanggal_mulai' => '2025-08-01', 'tanggal_akhir' => '2026-01-31',
        'tanggal_krs_awal' => '2025-08-01', 'tanggal_krs_akhir' => '2025-08-14', 'status' => true]);
}

it('redirects /dashboard to the role home page', function (string $state, string $route) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/dashboard')
        ->assertRedirect(route($route));
})->with([
    ['admin', 'admin.dashboard'],
    ['dosen', 'dosen.dashboard'],
    ['mahasiswa', 'mahasiswa.dashboard'],
]);

it('keeps the generic dashboard for accounts without any dashboard permission', function () {
    $user = User::factory()->withRole(Role::factory()->withPermissions(['admin.ruang'])->create())->create();

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('Dashboard'));
});

it('shows statistics, billing summary, and pending work on the admin dashboard', function () {
    $ta = taAktifDashboard();
    $lunas = User::factory()->mahasiswa()->create();
    $menunggu = User::factory()->mahasiswa()->create();
    User::factory()->mahasiswa()->create();
    TagihanSemester::create(['mahasiswa_id' => $lunas->mahasiswaProfile->id, 'tahun_akademik_id' => $ta->id, 'status' => TagihanSemester::LUNAS, 'total' => 1000000]);
    TagihanSemester::create(['mahasiswa_id' => $menunggu->mahasiswaProfile->id, 'tahun_akademik_id' => $ta->id, 'status' => TagihanSemester::MENUNGGU, 'total' => 1500000]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('tahunAkademik.label', '2025/2026 Ganjil')
            ->where('statistik.mahasiswa_aktif', 3)
            ->where('tagihan.lunas', 1)
            ->where('tagihan.menunggu', 1)
            ->where('tagihan.belum_terbit', 1)
            ->where('tagihan.nominal_terbit', 2500000)
            ->where('tagihan.nominal_lunas', 1000000)
            ->where('tindakan.0.judul', 'Bukti bayar tagihan semester')
            ->where('tindakan.0.jumlah', 1)
            ->where('tindakan.0.tautan', route('admin.tagihan.index', ['tahun_akademik_id' => $ta->id, 'status' => TagihanSemester::MENUNGGU]))
            ->where('tindakan.1.judul', 'Mahasiswa aktif belum ditagih'));
});

it('hides sections the staff role has no permission for', function () {
    taAktifDashboard();
    $staf = User::factory()->withRole(Role::factory()->withPermissions(['admin.dashboard'])->create())->create();

    $this->actingAs($staf)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('statistik', [])
        ->where('tagihan', null)
        ->where('buktiTerbaru', null)
        ->where('perkuliahanHariIni', null)
        ->where('tindakan', null)
        ->where('mahasiswaPerProdi', null)
        ->where('tahunAkademik.tanggal_krs_awal', null)
        ->where('pengingatTugasAkhir', null));
});

it('shows a finance staff role only the billing summary, pending proofs, and billing work', function () {
    $ta = taAktifDashboard();
    $mhs = User::factory()->mahasiswa()->create(['name' => 'Budi Bayar']);
    TagihanSemester::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'tahun_akademik_id' => $ta->id, 'status' => TagihanSemester::MENUNGGU,
        'total' => 1500000, 'bukti_diunggah_at' => now()]);
    $keuangan = User::factory()->withRole(Role::factory()->withPermissions(['admin.dashboard', 'admin.jenis-biaya', 'admin.tagihan'])->create())->create();

    $this->actingAs($keuangan)->get(route('admin.dashboard'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('statistik', [])
        ->where('mahasiswaPerProdi', null)
        ->where('perkuliahanHariIni', null)
        ->where('tahunAkademik.tanggal_krs_awal', null)
        ->where('tagihan.menunggu', 1)
        ->where('tindakan.0.judul', 'Bukti bayar tagihan semester')
        ->has('buktiTerbaru', 1)
        ->where('buktiTerbaru.0.jenis', 'Semester')
        ->where('buktiTerbaru.0.mahasiswa', 'Budi Bayar')
        ->where('buktiTerbaru.0.total', 1500000)
        ->where('buktiTerbaru.0.tautan', route('admin.tagihan.index', ['tahun_akademik_id' => $ta->id, 'status' => TagihanSemester::MENUNGGU])));
});

it('sends only the statistic cards the role may open', function () {
    taAktifDashboard();
    $staf = User::factory()->withRole(Role::factory()->withPermissions(['admin.dashboard', 'admin.pengajuan-cuti'])->create())->create();

    $this->actingAs($staf)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('statistik', ['mahasiswa_cuti' => 0])
        ->where('tindakan', [])
        ->where('mahasiswaPerProdi', null));
});

it('shows the total student and course cards to a full admin', function () {
    taAktifDashboard();
    User::factory()->mahasiswa()->create();
    User::factory()->mahasiswa()->create()->mahasiswaProfile->update(['status' => 'Lulus']);

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('statistik.mahasiswa', 2)
        ->where('statistik.mahasiswa_aktif', 1)
        ->where('statistik.mahasiswa_lulus', 1)
        ->has('statistik.mata_kuliah')
        ->has('statistik.program_studi'));
});

it('shows a Prodi account the number cards of its own study program only', function () {
    [$prodi, $kelasA] = roleProdiSetup();
    $prodiId = $kelasA->mataKuliah->prodi_id;
    User::factory()->dosen()->create()->dosenProfile->update(['prodi_id' => $prodiId, 'status' => 'Aktif']);

    $this->actingAs($prodi)->get(route('admin.dashboard'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('statistik.mahasiswa', 1)
        ->where('statistik.mahasiswa_aktif', 1)
        ->where('statistik.mata_kuliah', 1)
        ->where('statistik.dosen_aktif', DosenProfile::query()->where('prodi_id', $prodiId)->where('status', 'Aktif')->count())
        ->missing('statistik.program_studi'));
});

it('shows campus number cards on the lecturer home page', function () {
    taAktifDashboard();
    User::factory()->mahasiswa()->create();
    $dosen = User::factory()->dosen()->create();

    $this->actingAs($dosen)->get(route('dosen.dashboard'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dosen/Dashboard')
        ->where('statistikKampus.mahasiswa', 1)
        ->where('statistikKampus.mahasiswa_aktif', 1)
        ->has('statistikKampus.dosen_aktif')
        ->has('statistikKampus.program_studi')
        ->missing('statistikKampus.kelas_kuliah'));
});
