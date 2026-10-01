<?php

use App\Models\BadanHukum;
use App\Models\Kota;
use App\Models\PengaturanInstitusi;
use App\Models\Provinsi;
use App\Models\User;
use App\UserType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets admin open the institution settings page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.perguruan-tinggi.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/PerguruanTinggi')
            ->where('institusi.nama_pt', 'SIA VD')
        );
});

it('blocks non admins from the institution settings page', function (UserType $type) {
    $user = User::factory()->ofType($type)->create();

    $this->actingAs($user)->get(route('admin.perguruan-tinggi.edit'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.perguruan-tinggi.update'), ['nama_pt' => 'Kampus Lain'])->assertForbidden();
})->with([
    [UserType::Dosen],
    [UserType::Mahasiswa],
]);

it('lets admin update the institution settings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.perguruan-tinggi.update'), [
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

    $this->actingAs($admin)->put(route('admin.perguruan-tinggi.update'), [
        'nama_pt' => 'Universitas Contoh',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertSessionHasNoErrors();

    $logo = PengaturanInstitusi::current()->logo;
    Storage::disk('public')->assertExists($logo);

    expect(PengaturanInstitusi::current()->logo_url)->toBe('/storage/'.$logo);

    $this->actingAs($admin)->put(route('admin.perguruan-tinggi.update'), [
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

    $this->actingAs($admin)->put(route('admin.perguruan-tinggi.update'), [
        'nama_pt' => '',
        'logo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        'email' => 'bukan-email',
        'website' => 'bukan-url',
        'tahun_berdiri' => 900,
    ])->assertSessionHasErrors(['nama_pt', 'logo', 'email', 'website', 'tahun_berdiri']);
});

it('menyimpan data legal, alamat, dan badan hukum perguruan tinggi', function () {
    $admin = User::factory()->admin()->create();
    $badanHukum = BadanHukum::current();
    $sulsel = Provinsi::query()->create(['kode' => '190000', 'nama' => 'Prov. Sulawesi Selatan']);
    $jabar = Provinsi::query()->create(['kode' => '020000', 'nama' => 'Prov. Jawa Barat']);
    $makassar = Kota::query()->create(['provinsi_id' => $sulsel->id, 'kode' => '196000', 'nama' => 'Kota Makassar']);

    $isian = [
        'nama_pt' => 'STIKes Yapika',
        'badan_hukum_id' => $badanHukum->id,
        'nomor_akta_terakhir' => '7',
        'tanggal_akta_terakhir' => '2021-03-04',
        'nomor_pengesahan' => 'AHU-0002.AH.01.04.2021',
        'tanggal_pengesahan' => '2021-04-05',
        'akreditasi' => 'Baik Sekali',
        'alamat' => 'Jl. Sultan Alauddin No. 98',
        'alamat_lain' => 'Kampus 2, Jl. Contoh No. 1',
        'provinsi_id' => $sulsel->id,
        'kota_id' => $makassar->id,
        'kode_pos' => '90221',
        'telepon' => '0411-883619',
        'faximili' => '0411-883620',
    ];

    $this->actingAs($admin)->put(route('admin.perguruan-tinggi.update'), $isian)->assertSessionHasNoErrors();

    $institusi = PengaturanInstitusi::current();
    expect($institusi->badan_hukum_id)->toBe($badanHukum->id)
        ->and($institusi->tanggal_pengesahan->format('Y-m-d'))->toBe('2021-04-05')
        ->and($institusi->akreditasi)->toBe('Baik Sekali')
        ->and($institusi->alamat_lain)->toBe('Kampus 2, Jl. Contoh No. 1')
        ->and($institusi->kota_id)->toBe($makassar->id)
        ->and($institusi->faximili)->toBe('0411-883620');

    $this->actingAs($admin)->get(route('admin.perguruan-tinggi.edit'))
        ->assertInertia(fn ($page) => $page->where('institusi.tanggal_akta_terakhir', '2021-03-04')->has('badanHukums', 1)->has('kotas', 1));

    $this->actingAs($admin)->put(route('admin.perguruan-tinggi.update'), [...$isian, 'provinsi_id' => $jabar->id])->assertSessionHasErrors('kota_id');
    $this->actingAs($admin)->put(route('admin.perguruan-tinggi.update'), [...$isian, 'badan_hukum_id' => 999])->assertSessionHasErrors('badan_hukum_id');

    // Kota yang dipakai perguruan tinggi tidak bisa dihapus.
    $this->actingAs($admin)->delete(route('admin.kota.destroy', $makassar))->assertSessionHas('error');
    expect($makassar->fresh())->not->toBeNull();
});
