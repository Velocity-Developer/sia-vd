<?php

use App\LingkupProdi;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MataKuliah;
use App\Models\Role;
use App\Models\TahunAkademik;
use App\Models\User;
use App\PermissionCatalog;
use App\UserType;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Dua prodi masing-masing dengan satu kelas dan satu mahasiswa ber-KRS, plus akun Prodi untuk prodi pertama.
 *
 * @return array{0: User, 1: KelasKuliah, 2: KelasKuliah, 3: Krs, 4: Krs}
 */
function roleProdiSetup(): array
{
    $kelasA = createMateriKelasKuliah();
    $kelasB = createMateriKelasKuliah();
    $krs = [];
    foreach ([$kelasA, $kelasB] as $i => $kelas) {
        $mhs = User::factory()->mahasiswa()->create()->mahasiswaProfile;
        $mhs->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'nim' => "2301P00{$i}", 'angkatan' => 2023]);
        $krs[] = Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif', 'nilai' => 'A']);
    }

    $prodi = User::factory()->admin()->create(['role_id' => Role::query()->where('slug', LingkupProdi::ROLE)->value('id')]);
    $prodi->adminProfile->update(['prodi_id' => $kelasA->mataKuliah->prodi_id]);

    return [$prodi->fresh(), $kelasA, $kelasB, $krs[0], $krs[1]];
}

/**
 * @return array<string, mixed>
 */
function karyawanProdiPayload(int $roleId, ?int $prodiId): array
{
    return [
        'role_id' => $roleId, 'prodi_id' => $prodiId, 'name' => 'Operator Prodi', 'username' => 'operator.prodi', 'email' => 'operator.prodi@kampus.test',
        'nomor_induk' => 'KRY-P01', 'tempat_lahir' => 'Makassar', 'tanggal_lahir' => '1990-01-02', 'jenis_kelamin' => 'Perempuan',
        'agama' => 'Islam', 'no_telepon' => '0812', 'alamat' => 'Jl. Kampus', 'kewarganegaraan' => 'Indonesia',
        'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
    ];
}

it('creates the Prodi system role with the default permissions', function () {
    $role = Role::query()->where('slug', LingkupProdi::ROLE)->firstOrFail();

    expect($role->is_system)->toBeTrue()
        ->and($role->permissionKeys())->toContain('admin.khs', 'admin.mata-kuliah', 'admin.verifikasi-krs')
        ->and($role->permissionKeys())->not->toContain('admin.users.mahasiswa', 'admin.nilai-kkm', 'admin.komponen-nilai')
        ->and(collect(PermissionCatalog::IZIN_PRODI)->diff(collect(PermissionCatalog::definitions())->pluck('key')))->toBeEmpty();
});

it('requires a program studi for Prodi accounts and clears it for other roles', function () {
    [, $kelasA] = roleProdiSetup();
    $admin = User::factory()->admin()->create();
    $roleProdi = Role::query()->where('slug', LingkupProdi::ROLE)->value('id');
    $prodiId = $kelasA->mataKuliah->prodi_id;

    $this->actingAs($admin)->post(route('admin.users.karyawan.store'), karyawanProdiPayload($roleProdi, null))->assertSessionHasErrors('prodi_id');
    $this->actingAs($admin)->post(route('admin.users.karyawan.store'), karyawanProdiPayload($roleProdi, $prodiId))->assertSessionHasNoErrors();
    $akun = User::query()->where('username', 'operator.prodi')->firstOrFail();
    expect($akun->adminProfile->prodi_id)->toBe($prodiId)->and(LingkupProdi::id($akun))->toBe($prodiId);

    $this->actingAs($admin)->put(route('admin.users.karyawan.update', $akun), karyawanProdiPayload(Role::system(UserType::Admin)->id, $prodiId))
        ->assertSessionHasNoErrors();
    expect($akun->fresh()->adminProfile->prodi_id)->toBeNull();
});

it('shows only data of the account program studi', function () {
    [$prodi, $kelasA, $kelasB, $krsA, $krsB] = roleProdiSetup();

    $this->actingAs($prodi)->get(route('admin.mata-kuliah.index'))
        ->assertInertia(fn ($page) => $page->where('mataKuliahs.total', 1)->where('mataKuliahs.data.0.id', $kelasA->matkul_id));
    $this->actingAs($prodi)->get(route('admin.transkrip-nilai.index'))->assertInertia(fn ($page) => $page->where('mahasiswa.total', 1));

    $this->actingAs($prodi)->get(route('admin.khs.show', $krsA->mahasiswa_id))->assertOk();
    $this->actingAs($prodi)->get(route('admin.khs.show', $krsB->mahasiswa_id))->assertNotFound();
    $this->actingAs($prodi)->get(route('admin.mata-kuliah.edit', $kelasB->matkul_id))->assertNotFound();
    $this->actingAs($prodi)->get(route('admin.kelas-kuliah.show', $kelasB))->assertNotFound();
    $this->actingAs($prodi)->get(route('admin.nilai-semester.show', $kelasB))->assertNotFound();
    $this->actingAs($prodi)->get(route('admin.presensi.kelas', $kelasB))->assertNotFound();

    // Admin biasa tidak dibatasi.
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.khs.show', $krsB->mahasiswa_id))->assertOk();
});

it('rejects saving data that belongs to another program studi', function () {
    [$prodi, $kelasA, $kelasB] = roleProdiSetup();
    $this->actingAs($prodi);

    expect(fn () => KelasKuliah::create(['kode_kelas' => 'X-A', 'tahun_akademik_id' => $kelasB->tahun_akademik_id, 'kapasitas' => 30, 'dosen_id' => $kelasB->dosen_id, 'matkul_id' => $kelasB->matkul_id]))
        ->toThrow(HttpException::class);
    expect(fn () => MataKuliah::create(['kode_matkul' => 'X001', 'nama_matkul' => 'Lain', 'sks' => 2, 'semester' => 1, 'jenis' => 'Wajib', 'prodi_id' => $kelasB->mataKuliah->prodi_id]))
        ->toThrow(HttpException::class);

    $mk = MataKuliah::create(['kode_matkul' => 'X002', 'nama_matkul' => 'Sendiri', 'sks' => 2, 'semester' => 1, 'jenis' => 'Wajib', 'prodi_id' => $kelasA->mataKuliah->prodi_id]);
    expect($mk->exists)->toBeTrue();
});

it('only lets Prodi view global academic data', function () {
    [$prodi] = roleProdiSetup();
    $tahun = TahunAkademik::query()->firstOrFail();

    $this->actingAs($prodi)->get(route('admin.tahun-akademik.index'))->assertOk();
    $this->actingAs($prodi)->get(route('admin.ruang.index'))->assertOk();
    $this->actingAs($prodi)->get(route('admin.predikat.index'))->assertOk();
    $this->actingAs($prodi)->get(route('admin.tahun-akademik.create'))->assertForbidden();
    $this->actingAs($prodi)->delete(route('admin.tahun-akademik.destroy', $tahun))->assertForbidden();
    $this->actingAs($prodi)->post(route('admin.ruang.store'), [])->assertForbidden();
    $this->actingAs($prodi)->post(route('admin.predikat.store'), [])->assertForbidden();
    $this->actingAs($prodi)->get(route('admin.users.mahasiswa'))->assertForbidden();

    expect(TahunAkademik::query()->whereKey($tahun->id)->exists())->toBeTrue();
});
