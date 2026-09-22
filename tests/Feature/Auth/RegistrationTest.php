<?php

use App\Models\User;

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::where('username', 'testuser')->exists())->toBeFalse();
});
