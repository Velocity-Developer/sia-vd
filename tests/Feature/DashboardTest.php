<?php

use App\Models\User;

test('root redirects guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users are sent to their role dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/dashboard')->assertRedirect(route('mahasiswa.dashboard'));
    $this->get(route('mahasiswa.dashboard'))->assertOk();
});
