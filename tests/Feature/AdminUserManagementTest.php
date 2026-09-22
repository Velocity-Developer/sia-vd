<?php

use App\Models\Fakultas;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use App\UserType;

function createProgramStudi(): ProgramStudi
{
    $dekan = User::factory()->dosen()->create();
    $fakultas = Fakultas::create(['kode_fakultas' => 'FTI', 'nama_fakultas' => 'Fakultas Teknologi Informasi', 'dekan_id' => $dekan->dosenProfile->id, 'tanggal_berdiri' => '2001-08-17', 'no_telp' => '021-5551001', 'email' => 'fti@example.ac.id']);

    return ProgramStudi::create(['fakultas_id' => $fakultas->id, 'kode_prodi' => 'TI-S1', 'nama_prodi' => 'Teknik Informatika', 'jenjang' => 'S1', 'status_akreditasi' => 'Unggul', 'tanggal_akreditasi_mulai' => '2022-06-01', 'tanggal_akreditasi_akhir' => '2027-06-01', 'kaprodi' => $dekan->dosenProfile->id, 'tahun_berdiri' => 2001]);
}

it('shows filtered users with pagination and admin under karyawan', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(11)->dosen()->create();
    User::factory()->mahasiswa()->create();

    $response = $this->actingAs($admin)->get(route('admin.users.dosen'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users.data', 10)
            ->where('users.total', 11)
            ->where('users.data.0.email', fn ($email) => is_string($email))
        );

    $response = $this->actingAs($admin)->get(route('admin.users.karyawan'));

    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.email', $admin->email)
    );
});

it('creates dosen and edits users with self-excluded unique fields', function () {
    $admin = User::factory()->admin()->create();
    $dosen = User::factory()->dosen()->create();
    $prodi = createProgramStudi();

    $payload = [
        'role_id' => Role::system(UserType::Dosen)->id,
        'name' => 'Dosen Baru', 'username' => 'dosen-baru', 'email' => 'baru@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'nidn' => '99999999', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2000-01-02',
        'jenis_kelamin' => 'Laki-laki', 'agama' => 'Islam', 'no_telepon' => '08123456789',
        'alamat' => 'Jl. Merdeka', 'kewarganegaraan' => 'Indonesia',
        'jabatan_fungsional' => 'Asisten Ahli', 'pendidikan_terakhir' => 'S2',
        'status_kepegawaian' => 'Tetap', 'prodi_id' => $prodi->id,
    ];

    $this->actingAs($admin)->post(route('admin.users.dosen.store'), $payload)->assertRedirect()->assertSessionHas('success', 'Dosen berhasil ditambahkan.');

    $this->actingAs($admin)->put(route('admin.users.dosen.update', $dosen), array_replace($payload, [
        'name' => 'Dosen Diubah', 'username' => $dosen->username, 'email' => $dosen->email, 'nidn' => $dosen->dosenProfile->nidn,
    ]))->assertRedirect()->assertSessionDoesntHaveErrors()->assertSessionHas('success', 'Dosen berhasil diperbarui.');

    expect(User::find($dosen->id)->name)->toBe('Dosen Diubah');

    $this->actingAs($admin)->delete(route('admin.users.dosen.destroy', $dosen))->assertRedirect()->assertSessionHas('success', 'Dosen berhasil dihapus.');
});

it('validates required profile fields and new dropdown values', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.users.dosen.store'), [])->assertSessionHasErrors([
        'role_id', 'name', 'username', 'email', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama',
        'no_telepon', 'alamat', 'kewarganegaraan', 'nidn', 'jabatan_fungsional',
        'pendidikan_terakhir', 'status_kepegawaian', 'password',
    ]);

    $dosenWali = User::factory()->dosen()->create();
    $prodi = createProgramStudi();
    $mahasiswaPayload = [
        'role_id' => Role::system(UserType::Mahasiswa)->id,
        'name' => 'Mahasiswa Lengkap', 'username' => 'mhs-lengkap', 'email' => 'lengkap@example.com',
        'nim' => '11111111', 'angkatan' => 2024, 'semester' => 2, 'status' => 'Aktif',
        'dosen_wali_id' => $dosenWali->dosenProfile->id, 'prodi_id' => $prodi->id, 'sekolah_asal' => 'SMA Negeri 1',
        'nisn' => '1234567890', 'email_alternatif' => 'alt@example.com',
        'nama_ayah_kandung' => 'Ayah', 'tanggal_lahir_ayah' => '1970-05-10', 'pendidikan_terakhir_ayah' => 'S1', 'pekerjaan_ayah' => 'Pegawai Negeri Sipil (PNS)', 'penghasilan_ayah' => 'Rp5.000.000 – Rp7.499.999', 'no_telepon_ayah' => '08111111111', 'email_ayah' => 'ayah@example.com', 'alamat_ayah' => 'Jl. Ayah',
        'nama_ibu_kandung' => 'Ibu', 'tanggal_lahir_ibu' => '1972-08-12', 'pendidikan_terakhir_ibu' => 'S1', 'pekerjaan_ibu' => 'Guru / Dosen', 'penghasilan_ibu' => 'Rp3.000.000 – Rp4.999.999', 'no_telepon_ibu' => '08122222222', 'email_ibu' => 'ibu@example.com', 'alamat_ibu' => 'Jl. Ibu',
        'tempat_lahir' => 'Jakarta', 'tanggal_lahir' => '2001-03-04', 'jenis_kelamin' => 'Perempuan',
        'agama' => 'Kristen Protestan', 'no_telepon' => '08123456789', 'alamat' => 'Jl. Baru',
        'kewarganegaraan' => 'Indonesia', 'password' => 'password123', 'password_confirmation' => 'password123',
    ];
    $this->actingAs($admin)->post(route('admin.users.mahasiswa.store'), $mahasiswaPayload)->assertRedirect()->assertSessionHas('success', 'Mahasiswa berhasil ditambahkan.');

    $existing = User::factory()->mahasiswa()->create();
    $existing->profile->update(['nim' => '12345678']);

    $this->actingAs($admin)->post(route('admin.users.mahasiswa.store'), array_replace($mahasiswaPayload, [
        'nim' => $existing->profile->nim, 'username' => 'mhs-baru', 'email' => 'mhs@example.com',
    ]))->assertSessionHasErrors('nim');

    $this->actingAs($admin)->post(route('admin.users.mahasiswa.store'), array_replace($mahasiswaPayload, [
        'nim' => '87654321', 'username' => 'mhs-baru', 'email' => 'mhs@example.com',
        'name' => 'Mahasiswa Baru', 'nisn' => '0987654321', 'email_alternatif' => 'alt2@example.com',
    ]))->assertRedirect()->assertSessionHas('success', 'Mahasiswa berhasil ditambahkan.');

    $user = User::where('username', 'mhs-baru')->firstOrFail();
    expect($user->profile->nim)->toBe('87654321')->and($user->profile->tanggal_lahir->toDateString())->toBe('2001-03-04')->and($user->profile->alamat)->toBe('Jl. Baru')->and($user->profile->nama_ayah_kandung)->toBe('Ayah')->and($user->profile->nama_ibu_kandung)->toBe('Ibu')->and($user->profile->tanggal_lahir_ayah->toDateString())->toBe('1970-05-10');
});

it('assigns only roles that match the managed user type', function () {
    $admin = User::factory()->admin()->create();
    $dosen = User::factory()->dosen()->create();
    $kaprodi = Role::factory()->type(UserType::Dosen)->withPermissions(['dosen.dashboard', 'admin.mata-kuliah'])->create(['name' => 'Kaprodi']);
    $payload = [
        'name' => $dosen->name, 'username' => $dosen->username, 'email' => $dosen->email,
        'nidn' => $dosen->dosenProfile->nidn, 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2000-01-02',
        'jenis_kelamin' => 'Laki-laki', 'agama' => 'Islam', 'no_telepon' => '08123456789',
        'alamat' => 'Jl. Merdeka', 'kewarganegaraan' => 'Indonesia',
        'jabatan_fungsional' => 'Lektor', 'pendidikan_terakhir' => 'S3',
        'status_kepegawaian' => 'Tetap', 'prodi_id' => createProgramStudi()->id,
    ];

    $this->actingAs($admin)->put(route('admin.users.dosen.update', $dosen), $payload + ['role_id' => Role::system(UserType::Mahasiswa)->id])
        ->assertSessionHasErrors('role_id');

    $this->actingAs($admin)->put(route('admin.users.dosen.update', $dosen), $payload + ['role_id' => $kaprodi->id])
        ->assertSessionDoesntHaveErrors();

    $dosen->refresh();
    expect($dosen->role_id)->toBe($kaprodi->id)
        ->and($dosen->dosenProfile)->not->toBeNull();
    $this->actingAs($dosen)->get(route('admin.mata-kuliah.index'))->assertOk();
    $this->actingAs($dosen)->get(route('admin.fakultas.index'))->assertForbidden();
});

it('prevents admins from moving their own account to a role without role management', function () {
    $admin = User::factory()->admin()->create();
    $staff = Role::factory()->type(UserType::Admin)->withPermissions(['admin.dashboard'])->create();
    $admin->adminProfile->update(['nomor_induk' => 'A0001']);

    $this->actingAs($admin)->put(route('admin.users.karyawan.update', $admin), [
        'role_id' => $staff->id, 'name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email,
        'nomor_induk' => 'A0001', 'tempat_lahir' => 'Jakarta', 'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'Laki-laki',
        'agama' => 'Islam', 'no_telepon' => '0811', 'alamat' => 'Jl. Admin', 'kewarganegaraan' => 'Indonesia',
    ])->assertSessionHasErrors('role_id');

    expect($admin->fresh()->role_id)->toBe(Role::system(UserType::Admin)->id);
});

it('blocks non-admin users from user management pages', function () {
    $user = User::factory()->dosen()->create();

    foreach (['dosen', 'mahasiswa', 'karyawan'] as $type) {
        $this->actingAs($user)->get(route('admin.users.'.$type))->assertForbidden();
    }
});
