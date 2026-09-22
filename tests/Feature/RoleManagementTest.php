<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function permissionIds(array $keys): array
{
    return Permission::whereIn('key', $keys)->pluck('id')->all();
}

it('lists roles with their user and permission counts', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->dosen()->create();

    $this->actingAs($admin)->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Roles')
            ->has('roles.data', 3)
            ->where('roles.data', fn ($roles) => collect($roles)->firstWhere('slug', 'dosen')['users_count'] === 2
                && collect($roles)->firstWhere('slug', 'admin')['permissions_count'] === Permission::whereNull('user_type')->count())
        );

    $this->actingAs($admin)->get(route('admin.roles.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/RoleForm')->has('permissionGroups')->has('userTypes', 3));
});

it('creates a role with its permissions', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Staf Akademik',
        'description' => 'Mengelola data akademik',
        'user_type' => 'admin',
        'permissions' => permissionIds(['admin.dashboard', 'admin.mata-kuliah']),
    ])->assertRedirect(route('admin.roles.index'))->assertSessionHas('success', 'Role berhasil ditambahkan.');

    $role = Role::where('name', 'Staf Akademik')->firstOrFail();
    expect($role->slug)->toBe('staf-akademik')
        ->and($role->is_system)->toBeFalse()
        ->and($role->permissionKeys())->toBe(['admin.dashboard', 'admin.mata-kuliah']);
});

it('validates role name and permissions that do not fit the user type', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Admin',
        'user_type' => 'admin',
        'permissions' => [],
    ])->assertSessionHasErrors('name');

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Asisten',
        'user_type' => 'admin',
        'permissions' => permissionIds(['admin.ruang', 'mahasiswa.krs']),
    ])->assertSessionHasErrors('permissions');

    expect(Role::where('name', 'Asisten')->exists())->toBeFalse();
});

it('updates role permissions and applies them to its users', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::factory()->withPermissions(['admin.ruang'])->create();
    $staff = User::factory()->withRole($role)->create();

    $this->actingAs($staff)->get(route('admin.fakultas.index'))->assertForbidden();

    $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => 'Staf Fakultas',
        'user_type' => 'admin',
        'permissions' => permissionIds(['admin.fakultas']),
    ])->assertRedirect(route('admin.roles.index'))->assertSessionHas('success', 'Role berhasil diperbarui.');

    expect($role->fresh()->name)->toBe('Staf Fakultas');
    $this->actingAs($staff->fresh())->get(route('admin.fakultas.index'))->assertOk();
    $this->actingAs($staff->fresh())->get(route('admin.ruang.index'))->assertForbidden();
});

it('locks the user type of roles that are in use or built in', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::factory()->create();
    User::factory()->withRole($role)->create();

    $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => $role->name, 'user_type' => 'dosen', 'permissions' => [],
    ])->assertSessionHasErrors('user_type');

    $this->actingAs($admin)->put(route('admin.roles.update', Role::system(UserType::Mahasiswa)), [
        'name' => 'Mahasiswa', 'user_type' => 'admin', 'permissions' => [],
    ])->assertSessionHasErrors('user_type');

    expect($role->fresh()->user_type)->toBe(UserType::Admin);
});

it('keeps role management access on the built in admin role and the editor own role', function () {
    $admin = User::factory()->admin()->create();
    $adminRole = Role::system(UserType::Admin);

    $this->actingAs($admin)->put(route('admin.roles.update', $adminRole), [
        'name' => 'Admin', 'user_type' => 'admin', 'permissions' => permissionIds(['admin.dashboard']),
    ])->assertSessionHasErrors('permissions');

    expect($adminRole->fresh()->hasPermission('admin.roles'))->toBeTrue();

    $customAdmin = Role::factory()->withPermissions(['admin.roles'])->create();
    $manager = User::factory()->withRole($customAdmin)->create();

    $this->actingAs($manager)->put(route('admin.roles.update', $customAdmin), [
        'name' => $customAdmin->name, 'user_type' => 'admin', 'permissions' => [],
    ])->assertSessionHasErrors('permissions');
});

it('does not delete roles that are still used by users', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::factory()->create();
    User::factory()->withRole($role)->create();

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'))
        ->assertSessionHas('error', "Role {$role->name} masih digunakan oleh 1 pengguna. Pindahkan pengguna ke role lain sebelum menghapus.");

    expect(Role::whereKey($role->id)->exists())->toBeTrue();
    expect(fn () => $role->delete())->toThrow(QueryException::class);
});

it('does not delete built in roles', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::system(UserType::Dosen);
    User::query()->where('role_id', $role->id)->delete();

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertSessionHas('error');

    expect(Role::whereKey($role->id)->exists())->toBeTrue();
});

it('deletes unused custom roles with their permission assignments', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::factory()->withPermissions(['admin.ruang'])->create();

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertSessionHas('success', 'Role berhasil dihapus.');

    expect(Role::whereKey($role->id)->exists())->toBeFalse()
        ->and(DB::table('permission_role')->where('role_id', $role->id)->exists())->toBeFalse();
});

it('forbids users without the role management permission', function () {
    $dosen = User::factory()->dosen()->create();
    $staff = User::factory()->withRole(Role::factory()->withPermissions(['admin.dashboard'])->create())->create();

    foreach ([$dosen, $staff] as $user) {
        $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.roles.store'), ['name' => 'X', 'user_type' => 'admin', 'permissions' => []])->assertForbidden();
    }
});

it('migrates the legacy role column into the roles table', function () {
    $migration = require database_path('migrations/2026_09_22_100001_move_user_role_to_roles_table.php');
    $admin = User::factory()->admin()->create();
    $dosen = User::factory()->dosen()->create();
    $mahasiswa = User::factory()->mahasiswa()->create();

    $migration->down();
    expect(DB::table('users')->pluck('role', 'id')->all())->toBe([$admin->id => 'admin', $dosen->id => 'dosen', $mahasiswa->id => 'mahasiswa']);

    DB::table('users')->where('id', $mahasiswa->id)->update(['role' => 'unknown']);
    $migration->up();

    expect($admin->fresh()->role_id)->toBe(Role::system(UserType::Admin)->id)
        ->and($dosen->fresh()->role_id)->toBe(Role::system(UserType::Dosen)->id)
        ->and($mahasiswa->fresh()->role_id)->toBe(Role::system(UserType::Mahasiswa)->id)
        ->and($admin->fresh()->homeRoute())->toBe('admin.dashboard');
});
