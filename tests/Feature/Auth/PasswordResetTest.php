<?php

use App\Models\User;
use App\Notifications\AturUlangKataSandi;
use Illuminate\Support\Facades\Notification;

test('halaman lupa kata sandi bisa dibuka', function () {
    $this->get('/forgot-password')->assertStatus(200);
});

test('tautan atur ulang dikirim saat memasukkan email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['login' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, AturUlangKataSandi::class);
});

test('tautan atur ulang dikirim saat memasukkan username', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['login' => $user->username])->assertSessionHas('status');

    Notification::assertSentTo($user, AturUlangKataSandi::class);
});

test('akun yang tidak ada tetap dijawab sama tanpa mengirim surel', function () {
    Notification::fake();

    $this->post('/forgot-password', ['login' => 'tidak-ada@example.com'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

test('halaman kata sandi baru bisa dibuka dari tautan surel', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['login' => $user->email]);

    Notification::assertSentTo($user, AturUlangKataSandi::class, function (AturUlangKataSandi $notification) use ($user) {
        $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))->assertStatus(200);

        return true;
    });
});

test('kata sandi bisa diatur ulang dan dipakai masuk', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['login' => $user->username]);

    Notification::assertSentTo($user, AturUlangKataSandi::class, function (AturUlangKataSandi $notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('login', absolute: false));

        $this->post('/login', ['username' => $user->username, 'password' => 'sandi-baru-123']);
        $this->assertAuthenticatedAs($user->fresh());

        return true;
    });
});

test('token yang salah ditolak', function () {
    $user = User::factory()->create();

    $this->post('/reset-password', [
        'token' => 'token-palsu',
        'email' => $user->email,
        'password' => 'sandi-baru-123',
        'password_confirmation' => 'sandi-baru-123',
    ])->assertSessionHasErrors('email');
});

test('kata sandi baru harus dikonfirmasi dan cukup panjang', function () {
    $user = User::factory()->create();

    $this->post('/reset-password', [
        'token' => 'apa-saja',
        'email' => $user->email,
        'password' => 'pendek',
        'password_confirmation' => 'beda',
    ])->assertSessionHasErrors('password');
});
