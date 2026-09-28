<?php

use App\Models\Role;
use App\Models\User;
use App\Notifications\VerifikasiEmail;
use App\UserType;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('akun belum terverifikasi diarahkan ke halaman verifikasi', function () {
    $user = User::factory()->mahasiswa()->unverified()->create();

    $this->actingAs($user)->get(route('mahasiswa.dashboard'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
});

test('surel verifikasi berbahasa indonesia dan kirim ulang berfungsi', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifikasiEmail::class, function (VerifikasiEmail $notifikasi) use ($user): bool {
        return str_starts_with($notifikasi->toMail($user)->subject, 'Verifikasi Alamat Email');
    });
});

test('mengganti email di profil membatalkan verifikasi dan mengirim tautan baru', function () {
    Notification::fake();
    $user = User::factory()->admin()->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => 'baru@kampus.test'])
        ->assertSessionHas('status', 'verification-link-sent');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifikasiEmail::class);
});

test('akun baru dari admin dikirimi tautan verifikasi dan admin bisa menandainya manual', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    $data = [
        'role_id' => Role::system(UserType::Admin)->id, 'name' => 'Rina', 'username' => 'rina', 'email' => 'rina@kampus.test',
        'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        'nomor_induk' => 'KRY-001', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1992-03-04', 'jenis_kelamin' => 'Perempuan',
        'agama' => 'Islam', 'no_telepon' => '0812', 'alamat' => 'Jl. Kampus 1', 'kewarganegaraan' => 'Indonesia',
    ];
    $this->actingAs($admin)->post(route('admin.users.karyawan.store'), $data)->assertSessionHas('success');

    $rina = User::where('username', 'rina')->firstOrFail();
    expect($rina->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($rina, VerifikasiEmail::class);

    $this->actingAs($admin)->post(route('admin.users.karyawan.verifikasi-email', $rina))->assertSessionHas('success');
    Notification::assertSentToTimes($rina, VerifikasiEmail::class, 2);

    $this->actingAs($admin)->put(route('admin.users.karyawan.tandai-terverifikasi', $rina))->assertSessionHas('success');
    expect($rina->fresh()->hasVerifiedEmail())->toBeTrue();

    // Email diganti admin: verifikasi batal dan tautan dikirim ke alamat baru.
    unset($data['password'], $data['password_confirmation']);
    $this->actingAs($admin)->put(route('admin.users.karyawan.update', $rina), [...$data, 'email' => 'rina.baru@kampus.test'])->assertSessionHas('success');
    expect($rina->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentToTimes($rina, VerifikasiEmail::class, 3);
});

test('gagal kirim surel tidak menggagalkan pembuatan akun', function () {
    $admin = User::factory()->admin()->create();
    $gagal = Mockery::mock(Dispatcher::class);
    $gagal->shouldReceive('send')->andThrow(new RuntimeException('SMTP mati'));
    $this->app->instance(Dispatcher::class, $gagal);

    $this->actingAs($admin)->post(route('admin.users.karyawan.store'), [
        'role_id' => Role::system(UserType::Admin)->id, 'name' => 'Budi', 'username' => 'budi', 'email' => 'budi@kampus.test',
        'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        'nomor_induk' => 'KRY-002', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1992-03-04', 'jenis_kelamin' => 'Laki-laki',
        'agama' => 'Islam', 'no_telepon' => '0812', 'alamat' => 'Jl. Kampus 1', 'kewarganegaraan' => 'Indonesia',
    ])->assertSessionHas('error', fn (string $pesan): bool => str_contains($pesan, 'surel verifikasi gagal dikirim'));

    expect(User::where('username', 'budi')->exists())->toBeTrue();
});
