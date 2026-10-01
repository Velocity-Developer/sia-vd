<?php

use App\Mail\SurelUji;
use App\Models\PengaturanInstitusi;
use App\Models\Role;
use App\Models\TemplateEmail;
use App\Models\User;
use App\Notifications\AturUlangKataSandi;
use App\Notifications\VerifikasiEmail;
use App\UserType;
use Illuminate\Support\Facades\Notification;

function isiTemplate(array $ubah = []): array
{
    return [...TemplateEmail::JENIS['atur_ulang_kata_sandi']['bawaan'], ...$ubah];
}

it('menampilkan template bawaan di halaman pengaturan email', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/pengaturan-sistem/email')
        ->assertInertia(fn ($page) => $page
            ->has('template', count(TemplateEmail::JENIS))
            ->where('template.0.jenis', 'atur_ulang_kata_sandi')
            ->where('template.0.diubah', false)
            ->where('template.0.isi.subjek', TemplateEmail::JENIS['atur_ulang_kata_sandi']['bawaan']['subjek']));
});

it('hanya pemegang izin email yang bisa mengubah template', function () {
    $staf = Role::factory()->type(UserType::Admin)->withPermissions(['admin.dashboard', 'admin.institusi'])->create();
    $user = User::factory()->withRole($staf)->create();

    $this->actingAs($user)->put('/pengaturan-sistem/email/template/uji', isiTemplate())->assertForbidden();
    $this->actingAs($user)->delete('/pengaturan-sistem/email/template/uji')->assertForbidden();
    $this->actingAs($user)->postJson('/pengaturan-sistem/email/template/uji/pratinjau', isiTemplate())->assertForbidden();
});

it('menolak jenis template yang tidak dikenal', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/pengaturan-sistem/email/template/sembarang', isiTemplate())
        ->assertNotFound();
});

it('menyimpan template dan memakainya di surel atur ulang kata sandi', function () {
    PengaturanInstitusi::current()->update(['nama_pt' => 'Universitas Contoh']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put('/pengaturan-sistem/email/template/atur_ulang_kata_sandi', [
        'subjek' => 'Reset sandi {username} — {institusi}',
        'sapaan' => 'Yth. {nama}',
        'isi' => "Paragraf pembuka untuk {email}.\n\n{tombol}\n\nBerlaku {menit} menit.\nBaris kedua.",
        'tombol' => 'Buat Sandi Baru',
        'penutup' => "Hormat kami,\nBAAK {institusi}",
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $pengguna = User::factory()->create(['name' => 'Siti Aminah', 'username' => 'siti01', 'email' => 'siti@contoh.test']);
    $surel = (new AturUlangKataSandi('token-uji'))->toMail($pengguna);
    $html = (string) $surel->render();

    expect($surel->subject)->toBe('Reset sandi siti01 — Universitas Contoh')
        ->and($html)->toContain('Yth. Siti Aminah')
        ->toContain('Paragraf pembuka untuk siti@contoh.test.')
        ->toContain('Buat Sandi Baru')
        ->toContain('token-uji')
        ->toContain('Berlaku 60 menit.<br>')
        ->toContain('BAAK Universitas Contoh')
        ->not->toMatch('/\{[a-z_]+\}/')
        ->not->toContain('Hello!')
        ->not->toContain('Regards')
        // Tombol diletakkan di antara paragraf pembuka dan penjelasan masa berlaku.
        ->and(strpos($html, 'Paragraf pembuka'))->toBeLessThan(strpos($html, 'Buat Sandi Baru'))
        ->and(strpos($html, 'Buat Sandi Baru'))->toBeLessThan(strpos($html, 'Berlaku 60 menit'));
});

it('memakai isi bawaan untuk surel verifikasi selama templatenya belum diubah', function () {
    PengaturanInstitusi::current()->update(['nama_pt' => 'Universitas Contoh']);
    $pengguna = User::factory()->create(['name' => 'Siti Aminah', 'username' => 'siti01']);

    $surel = (new VerifikasiEmail)->toMail($pengguna);

    expect($surel->subject)->toBe('Verifikasi Alamat Email — Universitas Contoh')
        ->and((string) $surel->render())->toContain('Halo Siti Aminah,')->toContain('Verifikasi Email')->toContain('/verify-email/');
});

it('meletakkan tombol di akhir bila penanda tombol tidak ditulis', function () {
    $surel = TemplateEmail::susun('verifikasi_email', [
        'subjek' => 'S', 'sapaan' => null, 'isi' => "Satu.\n\nDua.", 'tombol' => 'Klik', 'penutup' => null,
    ], [], 'https://contoh.test/tautan');
    $html = (string) $surel->render();

    expect(strpos($html, 'Dua.'))->toBeLessThan(strpos($html, 'Klik'));
});

it('menolak variabel yang tidak dikenal dan teks tombol kosong', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/pengaturan-sistem/email/template/atur_ulang_kata_sandi', isiTemplate([
            'subjek' => 'Halo {namaa}',
            'tombol' => '',
        ]))
        ->assertSessionHasErrors(['subjek' => 'Variabel tidak dikenal: {namaa}.', 'tombol']);

    // {tombol} dan {menit} tidak tersedia di surel uji.
    $this->actingAs(User::factory()->admin()->create())
        ->put('/pengaturan-sistem/email/template/uji', [
            'subjek' => 'Uji', 'isi' => "Isi {menit}\n\n{tombol}",
        ])
        ->assertSessionHasErrors('isi');

    expect(TemplateEmail::query()->count())->toBe(0);
});

it('mengembalikan template ke isi bawaan', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->put('/pengaturan-sistem/email/template/uji', ['subjek' => 'Ubahan', 'isi' => 'Isi ubahan'])->assertSessionHasNoErrors();
    expect(TemplateEmail::isiBerlaku('uji')['subjek'])->toBe('Ubahan');

    $this->actingAs($admin)->delete('/pengaturan-sistem/email/template/uji')->assertSessionHas('success');

    expect(TemplateEmail::isiBerlaku('uji'))->toBe(TemplateEmail::JENIS['uji']['bawaan']);
});

it('memakai template uji untuk surel uji', function () {
    TemplateEmail::query()->create(['jenis' => 'uji', 'subjek' => 'Tes ke {email}', 'isi' => 'Halo dari {institusi}.']);
    PengaturanInstitusi::current()->update(['nama_pt' => 'Universitas Contoh']);

    $surel = new SurelUji('tujuan@contoh.test');

    $surel->assertHasSubject('Tes ke tujuan@contoh.test');
    $surel->assertSeeInHtml('Halo dari Universitas Contoh.');
});

it('menampilkan pratinjau isian yang belum disimpan dengan nilai contoh', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/pengaturan-sistem/email/template/atur_ulang_kata_sandi/pratinjau', isiTemplate(['sapaan' => 'Hai {nama}']))
        ->assertOk()
        ->assertJsonPath('subjek', fn ($subjek) => str_starts_with($subjek, 'Atur Ulang Kata Sandi'))
        ->assertJson(fn ($json) => $json->where('html', fn ($html) => str_contains($html, 'Hai '.TemplateEmail::CONTOH['nama']))->etc());

    expect(TemplateEmail::query()->count())->toBe(0);
});

it('surel reset kata sandi tetap terkirim lewat notifikasi', function () {
    Notification::fake();
    $pengguna = User::factory()->create();

    $this->post('/forgot-password', ['email' => $pengguna->email]);

    Notification::assertSentTo($pengguna, AturUlangKataSandi::class);
});
