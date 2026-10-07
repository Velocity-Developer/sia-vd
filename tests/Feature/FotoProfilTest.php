<?php

use App\AllowedUpload;
use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake(AllowedUpload::DISK));

function dataKaryawanFoto(int $roleId): array
{
    return [
        'role_id' => $roleId, 'name' => 'Rina Foto', 'username' => 'rina.foto', 'email' => 'rina.foto@kampus.test',
        'nomor_induk' => 'KRY-F01', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1992-03-04', 'jenis_kelamin' => 'Perempuan',
        'agama' => 'Islam', 'no_telepon' => '0812', 'alamat' => 'Jl. Kampus 1', 'kewarganegaraan' => 'Indonesia',
    ];
}

it('menyimpan, mengganti, menampilkan, dan menghapus foto karyawan', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::system(UserType::Admin);
    $data = dataKaryawanFoto($role->id);

    $this->actingAs($admin)->post(route('admin.users.karyawan.store'), [
        ...$data, 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        'foto' => UploadedFile::fake()->image('rina.jpg', 300, 400),
    ])->assertSessionHasNoErrors();

    $rina = User::where('username', 'rina.foto')->firstOrFail();
    $fotoPertama = $rina->adminProfile->foto;
    expect($fotoPertama)->toStartWith('foto/karyawan/');
    Storage::disk(AllowedUpload::DISK)->assertExists($fotoPertama);

    // Detail dan form edit hanya menerima URL tampil, bukan path disk.
    $this->actingAs($admin)->get(route('admin.users.karyawan.show', $rina))
        ->assertInertia(fn ($page) => $page->where('user.foto_url', fn ($url) => str_contains($url, '/berkas/foto/'.$rina->id))->missing('user.foto'));
    $this->actingAs($admin)->get(route('berkas.foto', $rina))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    // Ganti foto: berkas lama dihapus.
    $this->actingAs($admin)->put(route('admin.users.karyawan.update', $rina), [...$data, 'foto' => UploadedFile::fake()->image('baru.png')])
        ->assertSessionHasNoErrors();
    $fotoKedua = $rina->adminProfile->fresh()->foto;
    expect($fotoKedua)->not->toBe($fotoPertama);
    Storage::disk(AllowedUpload::DISK)->assertMissing($fotoPertama);

    // Simpan tanpa foto baru tidak mengubah foto.
    $this->actingAs($admin)->put(route('admin.users.karyawan.update', $rina), $data)->assertSessionHasNoErrors();
    expect($rina->adminProfile->fresh()->foto)->toBe($fotoKedua);

    // Hapus foto.
    $this->actingAs($admin)->put(route('admin.users.karyawan.update', $rina), [...$data, 'hapus_foto' => '1'])->assertSessionHasNoErrors();
    expect($rina->adminProfile->fresh()->foto)->toBeNull();
    Storage::disk(AllowedUpload::DISK)->assertMissing($fotoKedua);
    $this->actingAs($admin)->get(route('berkas.foto', $rina))->assertNotFound();

    // Hapus akun ikut menghapus fotonya.
    $this->actingAs($admin)->put(route('admin.users.karyawan.update', $rina), [...$data, 'foto' => UploadedFile::fake()->image('lagi.jpg')]);
    $fotoKetiga = $rina->adminProfile->fresh()->foto;
    $this->actingAs($admin)->delete(route('admin.users.karyawan.destroy', $rina))->assertSessionHas('success');
    Storage::disk(AllowedUpload::DISK)->assertMissing($fotoKetiga);
});

it('menolak berkas foto yang bukan gambar atau terlalu besar', function () {
    $admin = User::factory()->admin()->create();
    $data = [...dataKaryawanFoto(Role::system(UserType::Admin)->id), 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'];

    $this->actingAs($admin)->post(route('admin.users.karyawan.store'), [...$data, 'foto' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrors('foto');
    $this->actingAs($admin)->post(route('admin.users.karyawan.store'), [...$data, 'foto' => UploadedFile::fake()->image('besar.jpg')->size(3000)])
        ->assertSessionHasErrors('foto');
    expect(User::where('username', 'rina.foto')->exists())->toBeFalse();
});

it('membatasi siapa yang boleh melihat foto', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();
    $lain = User::factory()->mahasiswa()->create();
    $dosen = User::factory()->dosen()->create();
    $admin = User::factory()->admin()->create();

    $path = UploadedFile::fake()->image('m.jpg')->store('foto/mahasiswa', AllowedUpload::DISK);
    $mahasiswa->mahasiswaProfile->update(['foto' => $path]);

    $this->actingAs($mahasiswa)->get(route('berkas.foto', $mahasiswa))->assertOk();
    $this->actingAs($admin)->get(route('berkas.foto', $mahasiswa))->assertOk();
    $this->actingAs($lain)->get(route('berkas.foto', $mahasiswa))->assertForbidden();
    $this->actingAs($dosen)->get(route('berkas.foto', $mahasiswa))->assertForbidden();
});

it('membagikan foto profil sebagai avatar pengguna yang masuk', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();
    $this->actingAs($mahasiswa)->get(route('profile.edit'))->assertInertia(fn ($page) => $page->where('auth.user.avatar', null)->where('bisaUbahFoto', true));

    Storage::disk(AllowedUpload::DISK)->put('foto/mahasiswa/andi.jpg', 'isi');
    $mahasiswa->mahasiswaProfile->forceFill(['foto' => 'foto/mahasiswa/andi.jpg'])->save();

    $this->actingAs($mahasiswa->fresh())->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('auth.user.avatar', User::urlFoto($mahasiswa->id, 'foto/mahasiswa/andi.jpg'))->missing('auth.user.foto'));
});

it('lets every user change and remove their own photo from profile settings', function (string $jenis) {
    $user = User::factory()->{$jenis}()->create();
    $folder = $user->folderFoto();

    $this->actingAs($user)->post(route('profile.foto'), ['foto' => UploadedFile::fake()->image('a.jpg', 200, 200)])
        ->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
    $pertama = $user->fresh()->profile->foto;
    expect($pertama)->toStartWith($folder.'/');

    $this->actingAs($user)->post(route('profile.foto'), ['foto' => UploadedFile::fake()->image('b.png', 200, 200)])->assertSessionHasNoErrors();
    $kedua = $user->fresh()->profile->foto;
    expect($kedua)->not->toBe($pertama);
    Storage::disk(AllowedUpload::DISK)->assertMissing($pertama);
    $this->actingAs($user)->get(route('berkas.foto', $user))->assertOk();

    $this->actingAs($user)->post(route('profile.foto'), ['hapus_foto' => true])->assertSessionHasNoErrors();
    expect($user->fresh()->profile->foto)->toBeNull();
    Storage::disk(AllowedUpload::DISK)->assertMissing($kedua);
})->with(['admin', 'dosen', 'mahasiswa']);

it('rejects invalid or missing photos from profile settings', function () {
    $user = User::factory()->dosen()->create();

    $this->actingAs($user)->post(route('profile.foto'), [])->assertSessionHasErrors('foto');
    $this->actingAs($user)->post(route('profile.foto'), ['foto' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHasErrors('foto');
    $this->actingAs($user)->post(route('profile.foto'), ['foto' => UploadedFile::fake()->image('a.jpg')->size(3000)])->assertSessionHasErrors('foto');
    expect($user->fresh()->profile->foto)->toBeNull();
});
