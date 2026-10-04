<?php

use App\LingkupProdi;
use App\Models\Role;
use App\Models\User;

it('lists every account type in Data Pengguna with filter and search', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Utama']);
    $dosen = User::factory()->dosen()->create(['name' => 'Dosen Satu']);
    $mahasiswa = User::factory()->mahasiswa()->create(['name' => 'Mahasiswa Satu']);
    $mahasiswa->mahasiswaProfile->update(['nim' => '2301999']);
    $prodi = User::factory()->admin()->create(['name' => 'Akun Prodi', 'role_id' => Role::query()->where('slug', LingkupProdi::ROLE)->value('id')]);

    $this->actingAs($admin)->get(route('admin.pengguna.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/DataPengguna')
            ->where('users.total', 4)
            ->where('users.data', fn ($users) => collect($users)->firstWhere('id', $prodi->id)['jenis'] === 'Prodi'
                && collect($users)->firstWhere('id', $prodi->id)['tipe_rute'] === 'karyawan'
                && collect($users)->firstWhere('id', $dosen->id)['jenis'] === 'Dosen'
                && collect($users)->firstWhere('id', $admin->id)['jenis'] === 'Karyawan'));

    $this->actingAs($admin)->get(route('admin.pengguna.index', ['jenis' => 'prodi']))
        ->assertInertia(fn ($page) => $page->where('users.total', 1)->where('users.data.0.id', $prodi->id));
    $this->actingAs($admin)->get(route('admin.pengguna.index', ['jenis' => 'karyawan']))
        ->assertInertia(fn ($page) => $page->where('users.total', 1)->where('users.data.0.id', $admin->id));
    $this->actingAs($admin)->get(route('admin.pengguna.index', ['search' => '2301999']))
        ->assertInertia(fn ($page) => $page->where('users.total', 1)->where('users.data.0.nomor_induk', '2301999'));
});

it('offers Create User choices and preselects the Prodi role', function () {
    $admin = User::factory()->admin()->create();
    $roleProdi = Role::query()->where('slug', LingkupProdi::ROLE)->value('id');

    $this->actingAs($admin)->get(route('admin.pengguna.buat'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/BuatPengguna')
            ->where('pilihan', fn ($pilihan) => collect($pilihan)->pluck('jenis')->all() === ['prodi', 'dosen', 'mahasiswa']));

    $this->actingAs($admin)->get(route('admin.users.karyawan.create', ['role' => 'prodi']))
        ->assertInertia(fn ($page) => $page->where('defaultRoleId', $roleProdi)->where('title', 'Tambah User - Prodi'));
});

it('forbids Tools user menus for accounts without user management permission', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();
    $dosen = User::factory()->dosen()->create();

    $this->actingAs($mahasiswa)->get(route('admin.pengguna.index'))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.pengguna.buat'))->assertForbidden();
});

it('opens the dosen Input Nilai menu with only their classes', function () {
    $kelasA = createMateriKelasKuliah();
    $kelasB = createMateriKelasKuliah($kelasA->tahunAkademik);

    $this->actingAs($kelasA->dosen->user)->get(route('dosen.input-nilai.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Kelas/KelasKuliahIndex')
            ->where('modeNilai', true)
            ->where('kelasKuliahs.total', 1)
            ->where('kelasKuliahs.data.0.id', $kelasA->id));

    $this->actingAs($kelasA->dosen->user)->get(route('dosen.kelas-kuliah.index'))
        ->assertInertia(fn ($page) => $page->where('modeNilai', false));
});
