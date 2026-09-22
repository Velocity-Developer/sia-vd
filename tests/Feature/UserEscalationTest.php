<?php

use App\Models\Role;
use App\Models\User;
use App\UserType;

function karyawanPayload(User $user, int $roleId, array $extra = []): array
{
    return [
        'role_id' => $roleId, 'name' => $user->name, 'username' => $user->username, 'email' => $user->email,
        'nomor_induk' => $user->adminProfile?->nomor_induk ?? 'A'.$user->id, 'tempat_lahir' => 'Jakarta', 'tanggal_lahir' => '1990-01-01',
        'jenis_kelamin' => 'Laki-laki', 'agama' => 'Islam', 'no_telepon' => '0811', 'alamat' => 'Jl. Admin', 'kewarganegaraan' => 'Indonesia',
        ...$extra,
    ];
}

function staffPengguna(): User
{
    $role = Role::factory()->type(UserType::Admin)->withPermissions(['admin.dashboard', 'admin.users.karyawan'])->create(['name' => 'Staf Kepegawaian']);

    return User::factory()->withRole($role)->create();
}

it('does not let a limited staff role create a full admin account', function () {
    $staf = staffPengguna();
    $calon = User::factory()->make();

    $this->actingAs($staf)
        ->post(route('admin.users.karyawan.store'), karyawanPayload($calon, Role::system(UserType::Admin)->id, [
            'nomor_induk' => 'A9999', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ]))
        ->assertSessionHasErrors('role_id');

    expect(User::where('username', $calon->username)->exists())->toBeFalse();
});

it('only offers roles the staff member may assign', function () {
    $staf = staffPengguna();

    $this->actingAs($staf)->get(route('admin.users.karyawan.create'))
        ->assertInertia(fn ($page) => $page->where('roles', fn ($roles) => ! collect($roles)->pluck('id')->contains(Role::system(UserType::Admin)->id)));
});

it('does not let a limited staff role edit, reset the password of, or delete a full admin', function () {
    $staf = staffPengguna();
    $admin = User::factory()->admin()->create();
    $passwordLama = $admin->password;

    $this->actingAs($staf)
        ->put(route('admin.users.karyawan.update', $admin), karyawanPayload($admin, $admin->role_id, ['password' => 'diambilalih1', 'password_confirmation' => 'diambilalih1']))
        ->assertForbidden();
    $this->actingAs($staf)->get(route('admin.users.karyawan.edit', $admin))->assertForbidden();
    $this->actingAs($staf)->delete(route('admin.users.karyawan.destroy', $admin))->assertSessionHas('error');

    expect($admin->fresh()->password)->toBe($passwordLama);
});

it('does not let an admin delete their own account', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)->delete(route('admin.users.karyawan.destroy', $admin))->assertSessionHas('error');

    expect($admin->fresh())->not->toBeNull();
});

it('knows who is the last role manager', function () {
    $admin = User::factory()->admin()->create();
    expect($admin->isLastRoleManager())->toBeTrue();

    User::factory()->admin()->create();
    expect($admin->isLastRoleManager())->toBeFalse();
});
