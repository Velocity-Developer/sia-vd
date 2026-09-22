<?php

use App\Models\PengaturanInstitusi;
use App\Models\User;
use App\UserType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets admin open the institution settings page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('institusi.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Institusi')
            ->where('institusi.nama_pt', 'SIA VD')
        );
});

it('blocks non admins from the institution settings page', function (UserType $type) {
    $user = User::factory()->ofType($type)->create();

    $this->actingAs($user)->get(route('institusi.edit'))->assertForbidden();
    $this->actingAs($user)->put(route('institusi.update'), ['nama_pt' => 'Kampus Lain'])->assertForbidden();
})->with([
    [UserType::Dosen],
    [UserType::Mahasiswa],
]);

it('lets admin update the institution settings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('institusi.update'), [
        'nama_pt' => 'Universitas Contoh',
        'singkatan' => 'UE',
        'npsn' => '12345678',
        'alamat' => 'Jl. Pendidikan No. 1, Bandung',
        'telepon' => '022-1234567',
        'email' => 'info@example.ac.id',
        'website' => 'https://example.ac.id',
        'tahun_berdiri' => 1998,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $institusi = PengaturanInstitusi::current();

    expect($institusi->nama_pt)->toBe('Universitas Contoh')
        ->and($institusi->singkatan)->toBe('UE')
        ->and($institusi->npsn)->toBe('12345678')
        ->and($institusi->tahun_berdiri)->toBe(1998)
        ->and($institusi->updated_by)->toBe($admin->id)
        ->and(PengaturanInstitusi::count())->toBe(1);
});

it('stores a new logo and replaces the previous one', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('institusi.update'), [
        'nama_pt' => 'Universitas Contoh',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertSessionHasNoErrors();

    $logo = PengaturanInstitusi::current()->logo;
    Storage::disk('public')->assertExists($logo);

    expect(PengaturanInstitusi::current()->logo_url)->toBe('/storage/'.$logo);

    $this->actingAs($admin)->put(route('institusi.update'), [
        'nama_pt' => 'Universitas Contoh',
        'logo' => UploadedFile::fake()->image('logo-baru.png'),
    ])->assertSessionHasNoErrors();

    $logoBaru = PengaturanInstitusi::current()->logo;

    expect($logoBaru)->not->toBe($logo);
    Storage::disk('public')->assertMissing($logo);
    Storage::disk('public')->assertExists($logoBaru);
});

it('validates the institution fields', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('institusi.update'), [
        'nama_pt' => '',
        'logo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        'email' => 'bukan-email',
        'website' => 'bukan-url',
        'tahun_berdiri' => 900,
    ])->assertSessionHasErrors(['nama_pt', 'logo', 'email', 'website', 'tahun_berdiri']);
});
