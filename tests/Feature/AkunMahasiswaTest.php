<?php

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('keeps a student name locked while other profile fields still update', function () {
    $mahasiswa = User::factory()->mahasiswa()->create(['name' => 'Nama Resmi']);

    $this->actingAs($mahasiswa)
        ->patch(route('profile.update'), ['name' => 'Nama Palsu', 'email' => 'baru@example.com'])
        ->assertSessionHasNoErrors();

    expect($mahasiswa->fresh()->name)->toBe('Nama Resmi')
        ->and($mahasiswa->fresh()->email)->toBe('baru@example.com');
});

it('still lets dosen change their own name', function () {
    $dosen = User::factory()->dosen()->create();

    $this->actingAs($dosen)->patch(route('profile.update'), ['name' => 'Nama Baru', 'email' => $dosen->email]);

    expect($dosen->fresh()->name)->toBe('Nama Baru');
});

it('opens the password reset flow for guests only', function () {
    // Alur atur ulang kata sandi kini tersedia (lihat PasswordResetTest), tetapi hanya untuk tamu.
    $this->get('/forgot-password')->assertOk();

    $this->actingAs(User::factory()->mahasiswa()->create())
        ->get('/forgot-password')
        ->assertRedirect();
});

it('does not run demo data in production', function () {
    app()->detectEnvironment(fn () => 'production');
    Artisan::call('db:seed', ['--force' => true]);
    app()->detectEnvironment(fn () => 'testing');

    expect(User::count())->toBe(0);
});

it('refuses to run the demo seeder in production', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});

it('does not reset a demo account password that was already changed', function () {
    $this->seed();
    $admin = User::where('username', 'admin')->first();
    $admin->update(['password' => 'kata-sandi-baru']);

    $this->seed();

    expect(Hash::check('kata-sandi-baru', $admin->fresh()->password))->toBeTrue();
});
