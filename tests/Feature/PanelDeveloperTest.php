<?php

use App\Feature;
use App\Models\LogPengaturanFitur;
use App\Models\PengaturanFitur;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Rute /dev dimuat saat boot hanya bila DEV_PANEL=true; di tes dimuat manual setelah config dinyalakan.
 */
function nyalakanPanelDev(): void
{
    config(['app.dev_panel' => true]);
    require base_path('routes/dev.php');
    Route::getRoutes()->refreshNameLookups();
}

function akunDeveloper(): User
{
    $user = User::factory()->admin()->create();
    $user->update(['role_id' => Role::query()->where('slug', Role::DEVELOPER)->value('id')]);

    return $user->fresh();
}

it('does not register the panel unless DEV_PANEL is on', function () {
    expect(config('app.dev_panel'))->toBeFalse()
        ->and(Route::has('dev.fitur.index'))->toBeFalse();
    $this->actingAs(akunDeveloper())->get('/dev/fitur')->assertNotFound();
});

it('only lets the developer role open the panel', function () {
    nyalakanPanelDev();

    $this->get(route('dev.fitur.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->admin()->create())->get(route('dev.fitur.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->put(route('dev.fitur.update', 'keuangan'), ['aktif' => false])->assertForbidden();

    $dev = akunDeveloper();
    expect($dev->homeRoute())->toBe('dev.fitur.index');
    $this->actingAs($dev)->get(route('dev.fitur.index'))
        ->assertInertia(fn ($page) => $page->component('Dev/Fitur')
            ->where('auth.developer', true)
            ->where('fitur.0', [
                'nama' => 'kelola_role', 'label' => 'Kelola Role', 'keterangan' => config('client.fitur.kelola_role.keterangan'),
                'bawaan' => true, 'terkunci' => false, 'override' => null, 'aktif' => true, 'butuh' => [], 'dibutuhkan' => [],
            ])
            ->has('log', 0));
});

it('toggles a feature, logs it, clears the cache, and goes back to the config default', function () {
    nyalakanPanelDev();
    $dev = akunDeveloper();
    expect(Feature::aktif('keuangan'))->toBeTrue()
        ->and(Cache::has('fitur.override'))->toBeTrue();

    $this->actingAs($dev)->put(route('dev.fitur.update', 'keuangan'), ['aktif' => false])->assertSessionHas('success', 'Keuangan dimatikan.');
    expect(Cache::has('fitur.override'))->toBeFalse()
        ->and(Feature::aktif('keuangan'))->toBeFalse()
        ->and(PengaturanFitur::query()->where('nama', 'keuangan')->value('diubah_oleh'))->toBe($dev->id);

    $this->actingAs($dev)->put(route('dev.fitur.update', 'keuangan'), ['aktif' => null])->assertSessionHas('success');
    expect(Feature::aktif('keuangan'))->toBeTrue()
        ->and(LogPengaturanFitur::query()->orderBy('id')->get(['aktif_lama', 'aktif_baru', 'diubah_oleh'])->toArray())->toBe([
            ['aktif_lama' => null, 'aktif_baru' => false, 'diubah_oleh' => $dev->id],
            ['aktif_lama' => false, 'aktif_baru' => null, 'diubah_oleh' => $dev->id],
        ]);

    $this->actingAs($dev)->get(route('dev.fitur.index'))->assertInertia(fn ($page) => $page->has('log', 2)->where('log.0.baru', null));
});

it('refuses locked features with 403 and shows them as locked', function () {
    nyalakanPanelDev();
    config(['client.fitur.keuangan.locked' => true]);
    $dev = akunDeveloper();

    $this->actingAs($dev)->get(route('dev.fitur.index'))->assertInertia(fn ($page) => $page->where('fitur.1.terkunci', true));
    $this->actingAs($dev)->put(route('dev.fitur.update', 'keuangan'), ['aktif' => false])->assertForbidden();
    $this->actingAs($dev)->put(route('dev.fitur.update', 'tidak_ada'), ['aktif' => false])->assertNotFound();
    $this->actingAs($dev)->put(route('dev.fitur.update', 'kelola_role'), ['aktif' => 'bukan'])->assertSessionHasErrors('aktif');
    $this->actingAs($dev)->put(route('dev.fitur.update', 'kelola_role'), [])->assertSessionHasErrors('aktif');

    expect(PengaturanFitur::query()->count())->toBe(0)
        ->and(LogPengaturanFitur::query()->count())->toBe(0);
});

it('validates dependencies and saves nothing when they fail', function () {
    nyalakanPanelDev();
    config(['client.fitur.kelola_role.butuh' => ['keuangan']]);
    $dev = akunDeveloper();

    // Mematikan keuangan akan ikut mematikan Kelola Role yang sedang aktif.
    $this->actingAs($dev)->put(route('dev.fitur.update', 'keuangan'), ['aktif' => false])
        ->assertSessionHasErrors(['aktif' => 'Fitur Kelola Role membutuhkan Keuangan. Matikan fitur itu dulu.']);
    expect(Feature::aktif('keuangan'))->toBeTrue();

    $this->actingAs($dev)->put(route('dev.fitur.update', 'kelola_role'), ['aktif' => false])->assertSessionHasNoErrors();
    $this->actingAs($dev)->put(route('dev.fitur.update', 'keuangan'), ['aktif' => false])->assertSessionHasNoErrors();

    // Menyalakan Kelola Role saat keuangan mati ditolak.
    $this->actingAs($dev)->put(route('dev.fitur.update', 'kelola_role'), ['aktif' => true])
        ->assertSessionHasErrors(['aktif' => 'Kelola Role membutuhkan fitur yang masih mati: Keuangan. Nyalakan fitur itu dulu.']);

    expect(PengaturanFitur::semua())->toBe(['kelola_role' => false, 'keuangan' => false])
        ->and(LogPengaturanFitur::query()->count())->toBe(2);
});

it('keeps the developer role out of the app: hidden from Kelola Role and never assignable', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::query()->where('slug', Role::DEVELOPER)->sole();
    $dev = akunDeveloper();

    expect($admin->canAssignRole($role))->toBeFalse()
        ->and($admin->canManage($dev))->toBeFalse()
        ->and($dev->canAssignRole($role))->toBeFalse();

    $this->actingAs($admin)->get(route('admin.roles.index'))
        ->assertInertia(fn ($page) => $page->where('roles.data', fn ($roles) => collect($roles)->doesntContain('slug', Role::DEVELOPER)));
    $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertNotFound();
    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertNotFound();
});

it('assigns and revokes the developer role from the command line', function () {
    User::factory()->admin()->create();
    $user = User::factory()->admin()->create(['username' => 'pengembang']);
    $mhs = User::factory()->mahasiswa()->create(['username' => 'mhs1']);

    expect(Artisan::call('sia:developer', ['username' => 'pengembang']))->toBe(0)
        ->and($user->fresh()->isDeveloper())->toBeTrue()
        ->and(Artisan::call('sia:developer', ['username' => 'mhs1']))->toBe(1)
        ->and($mhs->fresh()->isDeveloper())->toBeFalse()
        ->and(Artisan::call('sia:developer', ['username' => 'tidak-ada']))->toBe(1)
        ->and(Artisan::call('sia:developer', ['username' => 'pengembang', '--cabut' => true]))->toBe(0)
        ->and($user->fresh()->role->slug)->toBe(Role::system(UserType::Admin)->slug);
});

it('lets the developer manage roles while Kelola Role is off for admins, and admins then use those roles', function () {
    nyalakanPanelDev();
    config(['client.fitur.kelola_role.default' => false]);
    $dev = akunDeveloper();
    $admin = User::factory()->admin()->create();
    $izin = Permission::query()->whereIn('key', ['admin.dashboard', 'admin.ruang'])->pluck('id')->all();

    $this->actingAs($admin)->get(route('admin.roles.index'))->assertNotFound();
    $this->actingAs($admin)->get(route('dev.roles.index'))->assertForbidden();

    $this->actingAs($dev)->get(route('dev.roles.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/Roles')->where('rute', 'dev.roles')
            ->where('roles.data', fn ($roles) => collect($roles)->doesntContain('slug', Role::DEVELOPER)));
    $this->actingAs($dev)->get(route('dev.roles.create'))->assertInertia(fn ($page) => $page->component('Admin/RoleForm')->where('rute', 'dev.roles'));

    $this->actingAs($dev)->post(route('dev.roles.store'), ['name' => 'Staf Sarpras', 'user_type' => 'admin', 'permissions' => $izin])
        ->assertRedirect(route('dev.roles.index'))->assertSessionHas('success', 'Role berhasil ditambahkan.');
    $role = Role::query()->where('name', 'Staf Sarpras')->sole();
    expect($role->permissionKeys())->toEqualCanonicalizing(['admin.dashboard', 'admin.ruang']);

    $this->actingAs($dev)->put(route('dev.roles.update', $role), ['name' => 'Staf Sarpras', 'user_type' => 'admin', 'permissions' => [$izin[0]]])
        ->assertRedirect(route('dev.roles.index'));
    expect($role->fresh()->permissions()->count())->toBe(1);

    // Admin memakai role buatan developer saat menambah user, walau menu Kelola Role-nya mati.
    $this->actingAs($admin)->get(route('admin.users.karyawan.create'))
        ->assertInertia(fn ($page) => $page->where('roles', fn ($roles) => collect($roles)->pluck('name')->contains('Staf Sarpras')
            && collect($roles)->pluck('name')->doesntContain('Developer')));
    $this->actingAs($admin)->post(route('admin.users.karyawan.store'), [
        'role_id' => $role->id, 'name' => 'Budi Sarpras', 'username' => 'budi.sarpras', 'email' => 'budi@kampus.test',
        'nomor_induk' => 'KRY-009', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-01-02', 'jenis_kelamin' => 'Laki-laki',
        'agama' => 'Islam', 'no_telepon' => '0812', 'alamat' => 'Jl. Kampus 2', 'kewarganegaraan' => 'Indonesia',
        'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
    ])->assertSessionHasNoErrors();
    expect(User::query()->where('username', 'budi.sarpras')->value('role_id'))->toBe($role->id);

    $this->actingAs($dev)->delete(route('dev.roles.destroy', $role))->assertSessionHas('error');
    $this->actingAs($dev)->get(route('dev.roles.edit', Role::query()->where('slug', Role::DEVELOPER)->value('id')))->assertNotFound();
});
