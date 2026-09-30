<?php

use App\Models\Role;
use App\Models\User;
use App\UserType;

beforeEach(fn () => config(['client.fitur.kelola_role.default' => false]));

it('closes every Kelola Role page while the feature is off', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::factory()->type(UserType::Admin)->create(['name' => 'Staf']);

    $this->actingAs($admin)->get(route('admin.roles.index'))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.roles.create'))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'Baru', 'user_type' => 'admin', 'permissions' => []])->assertNotFound();
    $this->actingAs($admin)->put(route('admin.roles.update', $role), ['name' => 'Ganti', 'user_type' => 'admin', 'permissions' => []])->assertNotFound();
    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertNotFound();

    expect(Role::where('name', 'Baru')->exists())->toBeFalse()
        ->and($role->fresh()->name)->toBe('Staf');

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('fitur.kelola_role', false)
        ->where('auth.permissions', fn ($izin) => collect($izin)->contains(Role::SUPER_PERMISSION)));

    config(['client.fitur.kelola_role.default' => true]);
    $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();
});

it('still lets the admin give any existing role to a user', function () {
    $admin = User::factory()->admin()->create();
    $staf = Role::factory()->type(UserType::Admin)->withPermissions(['admin.dashboard', 'admin.users.karyawan'])->create(['name' => 'Staf Kepegawaian']);
    $calon = User::factory()->make();

    $this->actingAs($admin)->get(route('admin.users.karyawan.create'))
        ->assertInertia(fn ($page) => $page->where('roles', fn ($roles) => collect($roles)->contains('id', $staf->id)));

    $this->actingAs($admin)->post(route('admin.users.karyawan.store'), [
        'role_id' => $staf->id, 'name' => $calon->name, 'username' => $calon->username, 'email' => $calon->email,
        'nomor_induk' => 'A9998', 'tempat_lahir' => 'Jakarta', 'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'Laki-laki', 'agama' => 'Islam',
        'no_telepon' => '0811', 'alamat' => 'Jl. Admin', 'kewarganegaraan' => 'Indonesia', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
    ])->assertSessionHasNoErrors();

    expect(User::where('username', $calon->username)->value('role_id'))->toBe($staf->id);
});
