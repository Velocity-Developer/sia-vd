<?php

use App\Models\User;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/confirm-password');

    $response->assertStatus(200);
});

test('password can be confirmed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('password is not confirmed with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
});

test('kelola user dan role meminta konfirmasi kata sandi', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => null]);

    $this->get(route('admin.users.dosen'))->assertRedirect(route('password.confirm'));
    $this->get(route('admin.roles.index'))->assertRedirect(route('password.confirm'));

    // Aksi selain GET kembali ke halaman asal sesudah konfirmasi, bukan ke URL aksinya.
    $this->from(route('admin.roles.index'))->delete(route('admin.roles.destroy', 1))
        ->assertRedirect(route('password.confirm'));
    $this->post(route('password.confirm'), ['password' => 'password'])
        ->assertRedirect(route('admin.roles.index'));

    $this->get(route('admin.users.dosen'))->assertOk();
});

test('konfirmasi kata sandi tidak mendahului pengecekan izin', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();
    $this->actingAs($mahasiswa)->withSession(['auth.password_confirmed_at' => null]);

    $this->get(route('admin.roles.index'))->assertForbidden();
});
