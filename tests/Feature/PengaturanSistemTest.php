<?php

use App\Models\PengaturanInstitusi;
use App\Models\PengaturanTampilan;
use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('opens the first settings tab the user is allowed to see', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('pengaturan-sistem.index'))
        ->assertRedirect(route('pengaturan-sistem.institusi'));

    $staf = Role::factory()->type(UserType::Admin)->withPermissions(['admin.dashboard', 'admin.pengaturan-tampilan'])->create();
    $this->actingAs(User::factory()->withRole($staf)->create())
        ->get(route('pengaturan-sistem.index'))
        ->assertRedirect(route('pengaturan-sistem.tampilan'));

    $this->actingAs(User::factory()->dosen()->create())->get(route('pengaturan-sistem.index'))->assertForbidden();
});

it('renders every settings tab for admin', function (string $tab, string $komponen) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('pengaturan-sistem.'.$tab))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($komponen));
})->with([
    ['institusi', 'PengaturanSistem/Institusi'],
    ['email', 'PengaturanSistem/Email'],
    ['akademik', 'PengaturanSistem/Akademik'],
    ['tampilan', 'PengaturanSistem/Tampilan'],
]);

it('redirects the old settings addresses', function (string $lama, string $baru) {
    $this->actingAs(User::factory()->admin()->create())->get($lama)->assertRedirect($baru)->assertStatus(301);
})->with([
    ['/settings/institusi', '/pengaturan-sistem/institusi'],
    ['/settings/email', '/pengaturan-sistem/email'],
    ['/admin/pengaturan-akademik', '/pengaturan-sistem/akademik'],
]);

it('shares defaults derived from the institution until display settings are filled', function () {
    PengaturanInstitusi::current()->update(['nama_pt' => 'Universitas Contoh', 'singkatan' => 'UNCO']);

    $this->actingAs(User::factory()->admin()->create())->get(route('pengaturan-sistem.tampilan'))
        ->assertInertia(fn ($page) => $page
            ->where('tampilan.nama_aplikasi', 'UNCO')
            ->where('tampilan.favicon_url', '/favicon.ico')
            ->where('tampilan.login_judul', PengaturanTampilan::LOGIN_JUDUL_BAWAAN)
            ->where('tampilan.login_sorotan', true)
            ->where('tampilan.login_tata_letak', 'panel')
            ->where('tampilan.sidebar_bawaan', 'lebar'));
});

it('saves display settings with uploaded favicon and login background', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('pengaturan-tampilan.update'), [
        'nama_aplikasi' => 'SIAKAD Contoh',
        'login_judul' => 'Selamat datang',
        'login_teks' => 'Masuk dengan akun kampus.',
        'login_sorotan' => false,
        'login_tata_letak' => 'tengah',
        'sidebar_bawaan' => 'ringkas',
        'favicon' => UploadedFile::fake()->image('ikon.png', 64, 64),
        'login_gambar' => UploadedFile::fake()->image('kampus.jpg', 1200, 800),
    ])->assertSessionHas('success');

    $tampilan = PengaturanTampilan::current();
    Storage::disk('public')->assertExists([$tampilan->favicon, $tampilan->login_gambar]);

    // Halaman masuk dibuka sebagai tamu.
    $this->flushSession();
    $this->app['auth']->forgetGuards();
    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->where('tampilan.nama_aplikasi', 'SIAKAD Contoh')
        ->where('tampilan.favicon_url', '/storage/'.$tampilan->favicon)
        ->where('tampilan.login_judul', 'Selamat datang')
        ->where('tampilan.login_gambar_url', '/storage/'.$tampilan->login_gambar)
        ->where('tampilan.login_sorotan', false)
        ->where('tampilan.login_tata_letak', 'tengah')
        ->where('tampilan.sidebar_bawaan', 'ringkas'));

    // Judul & favicon juga ditulis server-side agar tab browser benar sejak halaman pertama.
    $this->get(route('login'))->assertSee('<link rel="icon" href="/storage/'.$tampilan->favicon.'">', false);

    // Menghapus berkas mengembalikan ke bawaan dan membuang berkas lama.
    $favicon = $tampilan->favicon;
    $this->actingAs($admin)->post(route('pengaturan-tampilan.update'), [
        'nama_aplikasi' => '', 'login_sorotan' => true, 'login_tata_letak' => 'panel', 'sidebar_bawaan' => 'lebar', 'hapus_favicon' => true,
    ])->assertSessionHas('success');
    Storage::disk('public')->assertMissing($favicon);
    expect(PengaturanTampilan::shared()['favicon_url'])->toBe('/favicon.ico')
        ->and(PengaturanTampilan::current()->nama_aplikasi)->toBeNull();
});

it('rejects svg favicons and non-image login backgrounds', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('pengaturan-tampilan.update'), [
        'login_sorotan' => true,
        'login_tata_letak' => 'melayang',
        'sidebar_bawaan' => 'lebar',
        'favicon' => UploadedFile::fake()->create('ikon.svg', 2, 'image/svg+xml'),
        'login_gambar' => UploadedFile::fake()->create('kampus.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors(['favicon', 'login_gambar', 'login_tata_letak']);
});

it('refreshes the shared name when the institution changes', function () {
    PengaturanInstitusi::current()->update(['nama_pt' => 'Lama', 'singkatan' => null]);
    expect(PengaturanTampilan::shared()['nama_aplikasi'])->toBe('Lama');

    PengaturanInstitusi::current()->update(['nama_pt' => 'Baru']);
    expect(PengaturanTampilan::shared()['nama_aplikasi'])->toBe('Baru');
});

it('limits the display tab to its own permission', function () {
    $staf = Role::factory()->type(UserType::Admin)->withPermissions(['admin.dashboard', 'admin.institusi'])->create();
    $user = User::factory()->withRole($staf)->create();

    $this->actingAs($user)->get(route('pengaturan-sistem.tampilan'))->assertForbidden();
    $this->actingAs($user)->post(route('pengaturan-tampilan.update'), ['login_sorotan' => true, 'login_tata_letak' => 'panel', 'sidebar_bawaan' => 'lebar'])->assertForbidden();
    $this->actingAs($user)->get(route('pengaturan-sistem.institusi'))->assertOk();
});
