<?php

use App\Models\PengaturanRecaptcha;
use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Http::fake menumpuk (aturan pertama yang menang), jadi jawaban Google diambil dari variabel ini.
function googleMenjawab(bool $sukses): void
{
    $GLOBALS['jawabanRecaptcha'] = $sukses;
}

beforeEach(fn () => Http::fake([
    PengaturanRecaptcha::URL_VERIFIKASI => fn () => Http::response(['success' => $GLOBALS['jawabanRecaptcha'] ?? false]),
]));

function aktifkanRecaptcha(): void
{
    PengaturanRecaptcha::current()->update(['aktif' => true, 'site_key' => str_repeat('s', 40), 'secret_key' => 'rahasia-captcha']);
}

it('hanya bisa dibuka pengguna yang punya izin', function () {
    $this->actingAs(User::factory()->mahasiswa()->create())->get('/pengaturan-sistem/recaptcha')->assertForbidden();

    $staf = Role::factory()->type(UserType::Admin)->withPermissions(['admin.dashboard', 'admin.institusi'])->create();
    $this->actingAs(User::factory()->withRole($staf)->create())->put('/pengaturan-sistem/recaptcha', ['aktif' => 0])->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get('/pengaturan-sistem/recaptcha')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('PengaturanSistem/Recaptcha')->where('pengaturan.aktif', false));
});

it('menyimpan kunci tanpa uji selama captcha mati dan menyembunyikan secret key', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put('/pengaturan-sistem/recaptcha', [
        'aktif' => 0,
        'site_key' => ' kunci-situs ',
        'secret_key' => 'rahasia-captcha',
    ])->assertSessionHasNoErrors();

    $pengaturan = PengaturanRecaptcha::current();
    expect($pengaturan->site_key)->toBe('kunci-situs')
        ->and($pengaturan->secret_key)->toBe('rahasia-captcha')
        ->and(DB::table('pengaturan_recaptcha')->value('secret_key'))->not->toBe('rahasia-captcha');
    Http::assertNothingSent();

    $this->actingAs($admin)->get('/pengaturan-sistem/recaptcha')
        ->assertInertia(fn ($page) => $page->where('pengaturan.secret_key_tersimpan', true)->missing('pengaturan.secret_key'));
});

it('menolak mengaktifkan captcha sebelum captcha uji lolos', function () {
    $admin = User::factory()->admin()->create();
    $isian = ['aktif' => 1, 'site_key' => str_repeat('s', 40), 'secret_key' => 'rahasia-captcha'];

    googleMenjawab(true);
    $this->actingAs($admin)->put('/pengaturan-sistem/recaptcha', $isian)->assertSessionHasErrors('token_uji');

    googleMenjawab(false);
    $this->actingAs($admin)->put('/pengaturan-sistem/recaptcha', [...$isian, 'token_uji' => 'token-salah'])->assertSessionHasErrors('token_uji');

    expect(PengaturanRecaptcha::current()->aktif)->toBeFalse();

    googleMenjawab(true);
    $this->actingAs($admin)->put('/pengaturan-sistem/recaptcha', [...$isian, 'token_uji' => 'token-benar'])->assertSessionHasNoErrors();

    expect(PengaturanRecaptcha::current()->aktif)->toBeTrue();
    Http::assertSent(fn (Request $request) => $request['secret'] === 'rahasia-captcha' && $request['response'] === 'token-benar');
});

it('tidak meminta uji ulang bila captcha sudah aktif dan kuncinya tidak diganti', function () {
    aktifkanRecaptcha();

    $this->actingAs(User::factory()->admin()->create())
        ->put('/pengaturan-sistem/recaptcha', ['aktif' => 1, 'site_key' => str_repeat('s', 40), 'secret_key' => ''])
        ->assertSessionHasNoErrors();

    expect(PengaturanRecaptcha::current()->secret_key)->toBe('rahasia-captcha');
    Http::assertNothingSent();
});

it('meminta site key dan secret key saat captcha diaktifkan', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/pengaturan-sistem/recaptcha', ['aktif' => 1])
        ->assertSessionHasErrors(['site_key', 'secret_key']);
});

it('menampilkan captcha di halaman masuk hanya bila aktif', function () {
    $this->get('/login')->assertInertia(fn ($page) => $page->where('recaptchaSiteKey', null));

    aktifkanRecaptcha();

    $this->get('/login')->assertInertia(fn ($page) => $page->where('recaptchaSiteKey', str_repeat('s', 40)));
});

it('mewajibkan captcha yang lolos verifikasi untuk masuk', function () {
    aktifkanRecaptcha();
    $user = User::factory()->create();

    googleMenjawab(true);
    $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('captcha');
    $this->assertGuest();

    googleMenjawab(false);
    $this->post('/login', ['username' => $user->username, 'password' => 'password', 'g-recaptcha-response' => 'token-salah'])
        ->assertSessionHasErrors('captcha');
    $this->assertGuest();

    googleMenjawab(true);
    $this->post('/login', ['username' => $user->username, 'password' => 'password', 'g-recaptcha-response' => 'token-benar']);
    $this->assertAuthenticated();
});

it('tidak memanggil google saat captcha mati', function () {
    $user = User::factory()->create();

    $this->post('/login', ['username' => $user->username, 'password' => 'password']);

    $this->assertAuthenticated();
    Http::assertNothingSent();
});
