<?php

use App\Models\Fakultas;
use App\Models\Kota;
use App\Models\ProgramStudi;
use App\Models\Provinsi;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function adminUser(): User
{
    return User::factory()->admin()->create();
}

function dosenUser(): User
{
    return User::factory()->dosen()->create();
}

function fakultasPayload(int $dekanId, string $suffix = ''): array
{
    return [
        'kode_fakultas' => 'FIK'.$suffix,
        'nama_fakultas' => 'Fakultas Ilmu Komputer'.$suffix,
        'dekan_id' => $dekanId,
        'tanggal_berdiri' => '2000-01-02',
        'no_telp' => '0211234567',
        'email' => 'fik'.$suffix.'@example.com',
    ];
}

function programStudiPayload(int $fakultasId, int $kaprodiId, string $suffix = ''): array
{
    return [
        'fakultas_id' => $fakultasId,
        'kode_prodi' => 'IF'.$suffix,
        'nama_prodi' => 'Informatika'.$suffix,
        'jenjang' => 'S1',
        'status_akreditasi' => 'Baik Sekali',
        'no_sk_akreditasi' => null,
        'tanggal_akreditasi_mulai' => '2024-01-01',
        'tanggal_akreditasi_akhir' => '2029-01-01',
        'kaprodi' => $kaprodiId,
        'tahun_berdiri' => 2010,
    ];
}

it('allows admins to list academic data with pagination props', function () {
    $admin = adminUser();
    $dosen = dosenUser();
    Fakultas::unguarded(function () use ($dosen): void {
        for ($index = 1; $index <= 11; $index++) {
            Fakultas::create(fakultasPayload($dosen->dosenProfile->id, (string) $index));
        }
    });
    ProgramStudi::unguarded(function () use ($dosen): void {
        $fakultas = Fakultas::firstOrFail();
        for ($index = 1; $index <= 11; $index++) {
            ProgramStudi::create(programStudiPayload($fakultas->id, $dosen->dosenProfile->id, (string) $index));
        }
    });

    $this->actingAs($admin)->get(route('admin.fakultas.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/Fakultas')->has('fakultas.data', 10)->where('fakultas.total', 11));
    $this->actingAs($admin)->get(route('admin.program-studi.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/ProgramStudi')->has('programStudis.data', 10)->where('programStudis.total', 11));
});

it('denies non-admin academic management access', function () {
    $user = dosenUser();
    foreach (['admin.fakultas.index', 'admin.program-studi.index'] as $route) {
        $this->actingAs($user)->get(route($route))->assertForbidden();
    }
});

it('creates updates and deletes fakultas with flash messages and relationships', function () {
    $admin = adminUser();
    $dekan = dosenUser()->dosenProfile;
    $payload = fakultasPayload($dekan->id);

    $this->actingAs($admin)->post(route('admin.fakultas.store'), $payload)
        ->assertRedirect()->assertSessionHas('success', 'Fakultas berhasil ditambahkan.');
    $fakultas = Fakultas::firstOrFail();
    expect($fakultas->dekan->is($dekan))->toBeTrue();

    $this->actingAs($admin)->put(route('admin.fakultas.update', $fakultas), array_replace($payload, ['nama_fakultas' => 'Fakultas Baru']))
        ->assertRedirect()->assertSessionHas('success', 'Fakultas berhasil diperbarui.');
    expect($fakultas->fresh()->nama_fakultas)->toBe('Fakultas Baru');

    $this->actingAs($admin)->delete(route('admin.fakultas.destroy', $fakultas))
        ->assertRedirect()->assertSessionHas('success', 'Fakultas berhasil dihapus.');
    expect(Fakultas::find($fakultas->id))->toBeNull();
});

it('validates fakultas input', function () {
    $this->actingAs(adminUser())->post(route('admin.fakultas.store'), [])->assertSessionHasErrors([
        'kode_fakultas', 'nama_fakultas', 'dekan_id', 'tanggal_berdiri', 'no_telp', 'email',
    ]);
});

it('creates updates and deletes program studi with nullable accreditation number', function () {
    $admin = adminUser();
    $dosen = dosenUser()->dosenProfile;
    $fakultas = Fakultas::unguarded(fn (): Fakultas => Fakultas::create(fakultasPayload($dosen->id)));
    $payload = programStudiPayload($fakultas->id, $dosen->id);

    $this->actingAs($admin)->post(route('admin.program-studi.store'), $payload)
        ->assertRedirect()->assertSessionHas('success', 'Program Studi berhasil ditambahkan.');
    $programStudi = ProgramStudi::firstOrFail();
    expect($programStudi->no_sk_akreditasi)->toBeNull()->and($programStudi->fakultas->is($fakultas))->toBeTrue();

    $this->actingAs($admin)->put(route('admin.program-studi.update', $programStudi), array_replace($payload, ['nama_prodi' => 'Sistem Informasi']))
        ->assertRedirect()->assertSessionHas('success', 'Program Studi berhasil diperbarui.');
    expect($programStudi->fresh()->nama_prodi)->toBe('Sistem Informasi');

    $this->actingAs($admin)->delete(route('admin.program-studi.destroy', $programStudi))
        ->assertRedirect()->assertSessionHas('success', 'Program Studi berhasil dihapus.');
    expect(ProgramStudi::find($programStudi->id))->toBeNull();
});

it('validates program studi input and foreign keys', function () {
    $this->actingAs(adminUser())->post(route('admin.program-studi.store'), [])->assertSessionHasErrors([
        'fakultas_id', 'kode_prodi', 'nama_prodi', 'jenjang', 'status_akreditasi', 'kaprodi', 'tahun_berdiri',
    ]);
    $this->actingAs(adminUser())->post(route('admin.program-studi.store'), programStudiPayload(999999, 999999))
        ->assertSessionHasErrors(['fakultas_id', 'kaprodi']);
});

it('menyimpan gelar, status, kaprodi/operator, SK Dikti, dan alamat program studi', function () {
    $admin = adminUser();
    $dosen = dosenUser()->dosenProfile;
    $fakultas = Fakultas::unguarded(fn (): Fakultas => Fakultas::create(fakultasPayload($dosen->id)));
    $sulsel = Provinsi::query()->create(['kode' => '190000', 'nama' => 'Prov. Sulawesi Selatan']);
    $jabar = Provinsi::query()->create(['kode' => '020000', 'nama' => 'Prov. Jawa Barat']);
    $makassar = Kota::query()->create(['provinsi_id' => $sulsel->id, 'kode' => '196000', 'nama' => 'Kota Makassar']);

    $payload = [
        ...programStudiPayload($fakultas->id, $dosen->id),
        'gelar_akademik' => 'Sarjana Keperawatan',
        'singkatan_gelar' => 'S.Kep.',
        'sks_lulus' => 144,
        'status_prodi' => 'Aktif',
        'nomor_kaprodi' => '081234567890',
        'operator' => 'Rina',
        'nomor_operator' => '081298765432',
        'no_sk_dikti' => '123/KPT/I/2015',
        'tanggal_sk_dikti' => '2015-06-01',
        'tanggal_berakhir_sk_dikti' => '2030-06-01',
        'alamat' => 'Jl. Sultan Alauddin No. 98',
        'provinsi_id' => $sulsel->id,
        'kota_id' => $makassar->id,
        'kode_pos' => '90221',
        'telepon' => '0411-883619',
        'faximili' => '0411-883620',
        'email' => 'keperawatan@example.ac.id',
        'website' => 'https://keperawatan.example.ac.id',
    ];

    $this->actingAs($admin)->post(route('admin.program-studi.store'), $payload)->assertSessionHasNoErrors();
    $prodi = ProgramStudi::query()->firstOrFail();

    expect($prodi->singkatan_gelar)->toBe('S.Kep.')
        ->and($prodi->sks_lulus)->toBe(144)
        ->and($prodi->status_prodi)->toBe('Aktif')
        ->and($prodi->tanggal_berakhir_sk_dikti->format('Y-m-d'))->toBe('2030-06-01')
        ->and($prodi->kota_id)->toBe($makassar->id)
        ->and($prodi->operator)->toBe('Rina');

    $this->actingAs($admin)->get(route('admin.program-studi.show', $prodi))
        ->assertInertia(fn (Assert $page) => $page->where('programStudi.kota.nama', 'Kota Makassar')->where('programStudi.provinsi.nama', 'Prov. Sulawesi Selatan'));
    $this->actingAs($admin)->get(route('admin.program-studi.edit', $prodi))
        ->assertInertia(fn (Assert $page) => $page->has('kotas', 1)->where('statusProdi', ProgramStudi::STATUS_PRODI));

    $this->actingAs($admin)->put(route('admin.program-studi.update', $prodi), [...$payload, 'provinsi_id' => $jabar->id])
        ->assertSessionHasErrors('kota_id');
    $this->actingAs($admin)->put(route('admin.program-studi.update', $prodi), [...$payload, 'status_prodi' => 'Entah', 'tanggal_berakhir_sk_dikti' => '2010-01-01', 'email' => 'x'])
        ->assertSessionHasErrors(['status_prodi', 'tanggal_berakhir_sk_dikti', 'email']);

    // Isian baru boleh kosong.
    $this->actingAs($admin)->put(route('admin.program-studi.update', $prodi), [...programStudiPayload($fakultas->id, $dosen->id), 'tanggal_sk_dikti' => '', 'sks_lulus' => ''])
        ->assertSessionHasNoErrors();
    expect($prodi->fresh()->sks_lulus)->toBeNull();

    // Kota yang dipakai program studi tidak bisa dihapus.
    $prodi->update(['provinsi_id' => $sulsel->id, 'kota_id' => $makassar->id]);
    $this->actingAs($admin)->delete(route('admin.kota.destroy', $makassar))->assertSessionHas('error');
});
