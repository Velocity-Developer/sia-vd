<?php

use App\Models\PengaturanEmail;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

it('hanya bisa dibuka pengguna yang punya izin', function () {
    $this->actingAs(User::factory()->mahasiswa()->create())->get('/settings/email')->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get('/settings/email')->assertOk();
});

it('menyimpan pengaturan smtp dan menyembunyikan kata sandinya', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put('/settings/email', [
        'mailer' => 'smtp',
        'host' => 'smtp.contoh.test',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'akun@contoh.test',
        'password' => 'rahasia-smtp',
        'from_address' => 'no-reply@contoh.test',
        'from_name' => 'SIA VD',
    ])->assertSessionHasNoErrors();

    $pengaturan = PengaturanEmail::current();

    expect($pengaturan->mailer)->toBe('smtp')
        ->and($pengaturan->password)->toBe('rahasia-smtp')
        // tersimpan terenkripsi, bukan teks polos
        ->and(DB::table('pengaturan_email')->value('password'))->not->toBe('rahasia-smtp')
        ->and(Crypt::decryptString(DB::table('pengaturan_email')->value('password')))->toBe('rahasia-smtp');

    // Halaman tidak pernah mengirim kata sandi ke browser.
    $this->actingAs($admin)->get('/settings/email')
        ->assertInertia(fn ($page) => $page->where('pengaturan.password_tersimpan', true)->missing('pengaturan.password'));
});

it('mempertahankan kata sandi lama bila kolomnya dikosongkan', function () {
    $admin = User::factory()->admin()->create();
    PengaturanEmail::current()->update(['mailer' => 'smtp', 'password' => 'sandi-lama']);

    $this->actingAs($admin)->put('/settings/email', [
        'mailer' => 'smtp',
        'host' => 'smtp.contoh.test',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'akun@contoh.test',
        'password' => '',
        'from_address' => 'no-reply@contoh.test',
        'from_name' => 'SIA VD',
    ])->assertSessionHasNoErrors();

    expect(PengaturanEmail::current()->password)->toBe('sandi-lama');
});

it('meminta host dan port saat metode smtp dipilih', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/settings/email', ['mailer' => 'smtp'])
        ->assertSessionHasErrors(['host', 'port', 'encryption', 'from_address']);
});

it('memakai pengaturan basis data sebagai konfigurasi mail', function () {
    PengaturanEmail::current()->update([
        'mailer' => 'smtp',
        'host' => 'smtp.contoh.test',
        'port' => 2525,
        'encryption' => 'ssl',
        'username' => 'akun@contoh.test',
        'password' => 'rahasia-smtp',
        'from_address' => 'no-reply@contoh.test',
        'from_name' => 'Institusi Uji',
    ]);

    PengaturanEmail::terapkan();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.contoh.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.smtp.password'))->toBe('rahasia-smtp')
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.from.address'))->toBe('no-reply@contoh.test');
});

it('mengirim surel uji ke alamat yang diisi', function () {
    Mail::fake();

    $this->actingAs(User::factory()->admin()->create())
        ->post('/settings/email/uji', ['email_tujuan' => 'tujuan@contoh.test'])
        ->assertSessionHas('success');

    Mail::assertSent(Mailable::class, fn ($mail) => $mail->hasTo('tujuan@contoh.test'));
});

it('menolak email tujuan yang tidak valid', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/settings/email/uji', ['email_tujuan' => 'bukan-email'])
        ->assertSessionHasErrors('email_tujuan');
});

it('mengirim nama route pengaturan email ke browser', function () {
    // Daftar route yang dikirim ke browser difilter per peran (config/ziggy.php);
    // bila pola route ini terlewat, halamannya gagal dibentuk di browser.
    $admin = User::factory()->admin()->create();

    $grup = config('ziggy.groups.'.$admin->grupRute());

    expect($grup)->toContain('pengaturan-email.*');
});
