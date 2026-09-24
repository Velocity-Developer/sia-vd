<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\UserType;

it('redirects users to role dashboard after login', function (UserType $type, string $route) {
    $user = User::factory()->ofType($type)->create();

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $response->assertRedirect(route($route, absolute: false));
})->with([
    [UserType::Admin, 'admin.dashboard'],
    [UserType::Dosen, 'dosen.dashboard'],
    [UserType::Mahasiswa, 'mahasiswa.dashboard'],
]);

it('redirects users without any dashboard permission to the generic dashboard', function () {
    $role = Role::factory()->withPermissions(['admin.ruang'])->create();
    $user = User::factory()->withRole($role)->create();

    $this->post('/login', ['username' => $user->username, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
});

it('rejects users from other role areas', function () {
    $user = User::factory()->dosen()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->get('/mahasiswa')->assertForbidden();
    $this->actingAs($user)->get('/dosen')->assertOk();
});

it('exposes admin user management routes only to admins', function () {
    $admin = User::factory()->admin()->create();
    $dosen = User::factory()->dosen()->create();

    foreach (['dosen', 'mahasiswa', 'karyawan'] as $userType) {
        $this->actingAs($admin)->get(route('admin.users.'.$userType))->assertOk();
        $this->actingAs($dosen)->get(route('admin.users.'.$userType))->assertForbidden();
    }
});

it('exposes role menu placeholder routes only to matching role', function () {
    $dosen = User::factory()->dosen()->create();
    $mahasiswa = User::factory()->mahasiswa()->create();

    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.index'))->assertOk();
    $this->actingAs($dosen)->get(route('dosen.jadwal-kuliah'))->assertRedirect(route('dosen.kelas-kuliah.index', absolute: false));
    $this->actingAs($dosen)->get(route('mahasiswa.khs'))->assertForbidden();
    $this->actingAs($mahasiswa)->get(route('mahasiswa.khs'))->assertOk();
    $this->actingAs($mahasiswa)->get(route('mahasiswa.khs.transkrip-nilai'))->assertOk();
    $this->actingAs($mahasiswa)->get(route('mahasiswa.perpustakaan.pinjaman-aktif'))->assertOk();
    $this->actingAs($mahasiswa)->get(route('dosen.jadwal-kuliah'))->assertForbidden();
});

it('grants access only to the menus allowed by a custom role', function () {
    $role = Role::factory()->withPermissions(['admin.dashboard', 'admin.ruang'])->create();
    $staff = User::factory()->withRole($role)->create();

    $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($staff)->get(route('admin.ruang.index'))->assertOk();
    $this->actingAs($staff)->get(route('admin.fakultas.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('admin.users.dosen'))->assertForbidden();
});

it('applies permission changes stored in the database immediately', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.ruang.index'))->assertOk();

    Role::system(UserType::Admin)->permissions()->detach(Permission::where('key', 'admin.ruang')->value('id'));

    $this->actingAs($admin->fresh())->get(route('admin.ruang.index'))->assertForbidden();
});

it('ignores permissions that do not match the role user type', function () {
    $role = Role::factory()->type(UserType::Admin)->create();
    $role->permissions()->attach(Permission::where('key', 'dosen.kelas-kuliah')->value('id'));
    $user = User::factory()->withRole($role)->create();

    expect($user->hasPermission('dosen.kelas-kuliah'))->toBeFalse();
    $this->actingAs($user)->get(route('dosen.kelas-kuliah.index'))->assertForbidden();
});

it('shares the role and permissions of the authenticated user with the frontend', function () {
    $dosen = User::factory()->dosen()->create();

    $this->actingAs($dosen)->get(route('dosen.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.role.slug', 'dosen')
            ->where('auth.permissions', ['dosen.dashboard', 'dosen.kelas-kuliah', 'dosen.jadwal', 'dosen.materi', 'dosen.tugas', 'dosen.quiz', 'dosen.presensi', 'dosen.mahasiswa-kelas'])
            ->missing('auth.user.role')
        );
});
