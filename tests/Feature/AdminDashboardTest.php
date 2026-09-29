<?php

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
        ->where('tagihan', null)
        ->where('perkuliahanHariIni', null)
        ->where('tindakan', [])
        ->where('pengingatTugasAkhir', null));
});
