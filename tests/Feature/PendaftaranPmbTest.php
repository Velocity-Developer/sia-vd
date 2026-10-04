<?php

use App\AllowedUpload;
use App\Models\Agama;
use App\Models\Cmb;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanPmb;
use App\Models\PengaturanRecaptcha;
use App\Models\Permission;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use App\Models\WilayahKecamatan;
use App\Notifications\AturUlangKataSandi;
use App\Notifications\VerifikasiEmail;
use App\UserType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake(AllowedUpload::DISK));

function periodePmbAktif(array $ubah = []): PengaturanPmb
{
    return PengaturanPmb::query()->create([
        'kode' => 'REG2610',
        'tahun_angkatan' => 2027,
        'tanggal_buka' => today()->subDay()->toDateString(),
        'tanggal_tutup' => today()->addMonth()->toDateString(),
        'tanggal_usm_mulai' => today()->addMonths(2)->toDateString(),
        'tanggal_usm_selesai' => today()->addMonths(2)->toDateString(),
        'tanggal_her' => today()->addMonths(3)->toDateString(),
        'nilai_minimal' => 60,
        'kapasitas' => 100,
        'biaya_pendaftaran' => 250000,
        'tanggal_pembayaran_mulai' => today()->toDateString(),
        'tanggal_pembayaran_selesai' => today()->addMonth()->toDateString(),
        'is_open' => true,
        ...$ubah,
    ]);
}

function isianPmb(array $ubah = []): array
{
    $prodi = ProgramStudi::query()->first() ?? createMateriKelasKuliah()->mataKuliah->prodi;

    return [
        'nama' => 'Andi Calon',
        'tempat_lahir' => 'Makassar',
        'tanggal_lahir' => '2008-05-17',
        'nama_ibu' => 'Siti',
        'agama_id' => Agama::query()->where('kode', '1')->value('id'),
        'jenis_kelamin' => 'L',
        'status_sipil' => 'B',
        'nik' => '7371010101080001',
        'kewarganegaraan' => 'ID',
        'jalan' => 'Jl. Sultan Alauddin',
        'dusun' => 'Mangasa',
        'rt' => '1',
        'rw' => '2',
        'kelurahan' => 'Gunung Sari',
        'wilayah_kecamatan_id' => WilayahKecamatan::query()->where('nama', 'like', 'Kec. Rappocini%')->value('id'),
        'kode_pos' => '90221',
        'email' => 'andi@example.com',
        'hp' => '081234567890',
        'penerima_kps' => false,
        'kelas' => 'R',
        'program_studi_id' => $prodi->id,
        'status_masuk' => 'B',
        'asal_sekolah' => 'SMA 1 Makassar',
        'nisn' => '0081234567',
        'foto' => UploadedFile::fake()->image('foto.jpg', 300, 400),
        'berkas_ijazah' => UploadedFile::fake()->create('ijazah.pdf', 300, 'application/pdf'),
        'berkas_transkrip' => UploadedFile::fake()->image('transkrip.png'),
        ...$ubah,
    ];
}

it('menampilkan formulir tertutup bila tidak ada periode aktif', function () {
    periodePmbAktif(['is_open' => false]);
    periodePmbAktif(['kode' => 'REG-LAMA', 'tanggal_buka' => '2020-01-01', 'tanggal_tutup' => '2020-02-01']);

    $this->get(route('pmb.daftar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Pmb/Daftar')->where('periode', null));

    $this->post(route('pmb.daftar.store'), isianPmb())
        ->assertSessionHasErrors('periode');
    expect(Cmb::query()->count())->toBe(0);
});

it('menerima pendaftaran selama periode aktif dan memberi nomor urut', function () {
    $periode = periodePmbAktif();

    $this->get(route('pmb.daftar'))
        ->assertInertia(fn (Assert $page) => $page->component('Pmb/Daftar')->where('periode.kode', 'REG2610')->where('periode.penuh', false)->has('opsi.kewarganegaraan'));

    $this->post(route('pmb.daftar.store'), isianPmb())->assertRedirect(route('pmb.selesai'));
    $cmb = Cmb::query()->firstOrFail();
    expect($cmb->pengaturan_pmb_id)->toBe($periode->id)
        ->and($cmb->nomor_pendaftaran)->toBe('REG2610-0001')
        ->and($cmb->status_pendaftaran)->toBeNull()
        ->and($cmb->nilai)->toBeNull();

    $this->get(route('pmb.selesai'))
        ->assertInertia(fn (Assert $page) => $page->component('Pmb/Selesai')->where('pendaftar.nomor_pendaftaran', 'REG2610-0001'));

    // NIK sama di periode yang sama ditolak.
    $this->post(route('pmb.daftar.store'), isianPmb())->assertSessionHasErrors('nik');

    $this->post(route('pmb.daftar.store'), isianPmb(['nik' => '7371010101080002']))->assertRedirect(route('pmb.selesai'));
    expect(Cmb::query()->latest('id')->value('nomor_pendaftaran'))->toBe('REG2610-0002');
});

it('mengabaikan periode, nilai, dan status dari formulir publik', function () {
    $periode = periodePmbAktif();
    $lain = periodePmbAktif(['kode' => 'REG-TUTUP', 'is_open' => false]);

    $this->post(route('pmb.daftar.store'), isianPmb(['pengaturan_pmb_id' => $lain->id, 'nilai' => 99, 'status_pendaftaran' => 'diterima']));

    expect(Cmb::query()->firstOrFail())->pengaturan_pmb_id->toBe($periode->id)->nilai->toBeNull()->status_pendaftaran->toBeNull();
});

it('menolak pendaftaran saat kuota penuh atau isian pindahan tidak lengkap', function () {
    $periode = periodePmbAktif(['kapasitas' => 1]);
    $this->post(route('pmb.daftar.store'), isianPmb())->assertRedirect(route('pmb.selesai'));

    $this->post(route('pmb.daftar.store'), isianPmb(['nik' => '7371010101080009']))->assertSessionHasErrors('periode');
    $this->get(route('pmb.daftar'))->assertInertia(fn (Assert $page) => $page->where('periode.penuh', true));

    $this->post(route('pmb.daftar.store'), isianPmb(['nik' => 'abc', 'penerima_kps' => true, 'kewarganegaraan' => 'XX']))
        ->assertSessionHasErrors(['nik', 'nomor_kps', 'kewarganegaraan']);
});

it('mewajibkan pas foto, ijazah, dan transkrip dengan format yang benar', function () {
    periodePmbAktif();

    $this->post(route('pmb.daftar.store'), isianPmb(['foto' => null, 'berkas_ijazah' => null, 'berkas_transkrip' => null]))
        ->assertSessionHasErrors(['foto', 'berkas_ijazah', 'berkas_transkrip']);

    $this->post(route('pmb.daftar.store'), isianPmb([
        'foto' => UploadedFile::fake()->create('foto.pdf', 100, 'application/pdf'),
        'berkas_ijazah' => UploadedFile::fake()->create('ijazah.html', 10, 'text/html'),
        'berkas_transkrip' => UploadedFile::fake()->create('transkrip.pdf', 3000, 'application/pdf'),
    ]))->assertSessionHasErrors(['foto', 'berkas_ijazah', 'berkas_transkrip' => 'Transkrip nilai maksimal 2 MB.']);

    expect(Cmb::query()->count())->toBe(0)
        ->and(Storage::disk(AllowedUpload::DISK)->allFiles())->toBe([]);
});

it('menghapus berkas yang sudah tersimpan bila pendaftaran gagal', function () {
    periodePmbAktif(['kapasitas' => 0]);

    $this->post(route('pmb.daftar.store'), isianPmb())->assertSessionHasErrors('periode');

    expect(Storage::disk(AllowedUpload::DISK)->allFiles())->toBe([]);
});

it('mewajibkan captcha di formulir bila captcha PMB dinyalakan', function () {
    Http::fake([PengaturanRecaptcha::URL_VERIFIKASI => Http::response(['success' => true])]);
    PengaturanRecaptcha::current()->update(['aktif_pmb' => true, 'site_key' => str_repeat('s', 40), 'secret_key' => 'rahasia']);
    periodePmbAktif();

    $this->post(route('pmb.daftar.store'), isianPmb())->assertSessionHasErrors('captcha');
    $this->post(route('pmb.daftar.store'), isianPmb(['g-recaptcha-response' => 'token']))->assertRedirect(route('pmb.selesai'));
});

it('mencari kecamatan berkode Feeder', function () {
    $this->getJson(route('pmb.kecamatan', ['q' => 'ra']))->assertExactJson([]);
    $this->getJson(route('pmb.kecamatan', ['q' => 'Rappocini']))
        ->assertOk()
        ->assertJsonFragment(['nama' => 'Kec. Rappocini, Kota Makassar, Prov. Sulawesi Selatan']);
});

it('admin mengisi nilai dan status pendaftar', function () {
    $admin = User::factory()->admin()->create();
    $periode = periodePmbAktif();
    $this->post(route('pmb.daftar.store'), isianPmb());
    $cmb = Cmb::query()->firstOrFail();

    $this->actingAs($admin)->get(route('admin.pendaftar-pmb.index', ['status' => 'menunggu']))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/PendaftarPmb')->where('pendaftar.total', 1));
    $this->actingAs($admin)->get(route('admin.pendaftar-pmb.show', $cmb))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/PendaftarPmbShow')
            ->where('pendaftar.kecamatan.kode', $cmb->kecamatan->kode)
            ->missing('pendaftar.foto')
            ->has('berkas', 3)
            ->where('berkas.1.label', 'Ijazah')
            ->where('berkas.1.gambar', false)
            ->where('berkas.0.gambar', true));

    // Berkas tersimpan di disk privat dan hanya bisa diunduh pengelola data pendaftar.
    $berkas = array_values($cmb->only(array_keys(Cmb::BERKAS)));
    Storage::disk(AllowedUpload::DISK)->assertExists($berkas);
    $this->actingAs($admin)->get(route('berkas.pmb', [$cmb, 'berkas_ijazah']))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($admin)->get(route('berkas.pmb', [$cmb, 'nik']))->assertNotFound();
    $this->actingAs(User::factory()->mahasiswa()->create())->get(route('berkas.pmb', [$cmb, 'foto']))->assertForbidden();

    $this->actingAs($admin)->put(route('admin.pendaftar-pmb.update', $cmb), ['nilai' => 75.5, 'status_pendaftaran' => 'diterima'])->assertSessionHasNoErrors();
    expect($cmb->fresh())->nilai->toBe(75.5)->status_pendaftaran->toBe('diterima');

    $this->actingAs($admin)->put(route('admin.pendaftar-pmb.update', $cmb), ['nilai' => 120, 'status_pendaftaran' => 'lulus'])
        ->assertSessionHasErrors(['nilai', 'status_pendaftaran']);

    $this->actingAs($admin)->get(route('admin.pendaftar-pmb.index', ['status' => 'diterima']))
        ->assertInertia(fn (Assert $page) => $page->where('pendaftar.total', 1));

    // Periode yang sudah punya pendaftar tidak bisa dihapus.
    $this->actingAs($admin)->delete(route('admin.periode-pmb.destroy', $periode))->assertSessionHas('error');

    $this->actingAs($admin)->delete(route('admin.pendaftar-pmb.destroy', $cmb))->assertRedirect(route('admin.pendaftar-pmb.index'));
    expect(Cmb::query()->count())->toBe(0);
    Storage::disk(AllowedUpload::DISK)->assertMissing($berkas);
});

it('membatasi data pendaftar untuk pemegang izin', function () {
    $this->actingAs(User::factory()->mahasiswa()->create())->get(route('admin.pendaftar-pmb.index'))->assertForbidden();
});

function calonMabaLulus(array $ubah = []): Cmb
{
    PengaturanPmb::aktif() ?? periodePmbAktif();
    test()->post(route('pmb.daftar.store'), isianPmb($ubah))->assertRedirect(route('pmb.selesai'));
    $cmb = Cmb::query()->latest('id')->firstOrFail();
    $cmb->forceFill(['status_pendaftaran' => Cmb::STATUS_DITERIMA, 'nilai' => 80])->save();

    return $cmb;
}

it('menyalin calon maba lulus ke data mahasiswa sesuai pemetaan biodata', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $cmb = calonMabaLulus(['agama_id' => Agama::query()->where('kode', '2')->value('id'), 'penerima_kps' => true, 'nomor_kps' => 'KPS-1']);

    $this->actingAs($admin)->get(route('admin.pendaftar-pmb.index'))
        ->assertInertia(fn (Assert $page) => $page->where('bolehSalin', true)->where('pendaftar.data.0.mahasiswa_url', null));

    // NIM wajib diisi admin dan menjadi username.
    $this->actingAs($admin)->post(route('admin.pendaftar-pmb.salin', $cmb))->assertSessionHasErrors('nim');
    $this->actingAs($admin)->post(route('admin.pendaftar-pmb.salin', $cmb), ['nim' => '2701A001'])->assertSessionHas('success');

    $user = User::query()->where('username', '2701A001')->firstOrFail();
    $profil = $user->mahasiswaProfile;
    expect($user)->email->toBe('andi@example.com')->name->toBe('Andi Calon')
        ->and($user->type())->toBe(UserType::Mahasiswa)
        ->and($profil)->cmb_id->toBe($cmb->id)->nim->toBe('2701A001')->dosen_wali_id->toBeNull()
        ->status->toBe('Aktif')->angkatan->toBe(2027)->prodi_id->toBe($cmb->program_studi_id)->jalur_kelas->toBe('R')
        ->jenis_kelamin->toBe('Laki-laki')->agama->toBe('Kristen Protestan')->kewarganegaraan->toBe('Indonesia')
        ->no_telepon->toBe('081234567890')->alamat->toBe('Jl. Sultan Alauddin')->rt->toBe('1')->kelurahan->toBe('Gunung Sari')
        ->wilayah_kecamatan_id->toBe($cmb->wilayah_kecamatan_id)->nik->toBe($cmb->nik)->penerima_kps->toBeTrue()->nomor_kps->toBe('KPS-1')
        ->sekolah_asal->toBe('SMA 1 Makassar')->nisn->toBe('0081234567')->nama_ibu_kandung->toBe('Siti');

    // Berkas disalin ke folder mahasiswa; berkas pendaftar tetap ada.
    $disk = Storage::disk(AllowedUpload::DISK);
    expect($profil->foto)->toStartWith('foto/mahasiswa/')->and($profil->berkas_ijazah)->toStartWith('berkas/mahasiswa/')
        ->and($profil->berkas_ijazah)->not->toBe($cmb->berkas_ijazah);
    $disk->assertExists([$profil->foto, $profil->berkas_ijazah, $profil->berkas_transkrip, $cmb->berkas_ijazah]);

    Notification::assertSentTo($user, VerifikasiEmail::class);
    Notification::assertSentTo($user, AturUlangKataSandi::class);

    // Sudah disalin: tidak bisa disalin ulang, status terkunci Diterima, pendaftar tidak bisa dihapus.
    $this->actingAs($admin)->post(route('admin.pendaftar-pmb.salin', $cmb), ['nim' => '2701A099'])->assertSessionHas('error', 'Pendaftar ini sudah disalin ke Data Mahasiswa.');
    $this->actingAs($admin)->put(route('admin.pendaftar-pmb.update', $cmb), ['nilai' => 80, 'status_pendaftaran' => 'ditolak'])
        ->assertSessionHasErrors('status_pendaftaran');
    $this->actingAs($admin)->delete(route('admin.pendaftar-pmb.destroy', $cmb))->assertSessionHas('error');
    expect(User::query()->where('email', 'andi@example.com')->count())->toBe(1)->and($cmb->fresh())->not->toBeNull();

    $this->actingAs($admin)->get(route('admin.pendaftar-pmb.show', $cmb))
        ->assertInertia(fn (Assert $page) => $page->where('pendaftar.mahasiswa_url', route('admin.users.mahasiswa.show', $user)));

    // Berkas mahasiswa: pemilik dan pengelola Data Mahasiswa.
    $user->markEmailAsVerified();
    // Sesi terikat hash sandi (AuthenticateSession), jadi kosongkan sesi tiap berganti akun.
    $this->flushSession()->actingAs($user)->get(route('berkas.mahasiswa', [$user, 'berkas_ijazah']))->assertOk();
    $this->flushSession()->actingAs($admin)->get(route('berkas.mahasiswa', [$user, 'berkas_transkrip']))->assertOk();
    $this->flushSession()->actingAs(User::factory()->mahasiswa()->create())->get(route('berkas.mahasiswa', [$user, 'berkas_ijazah']))->assertForbidden();
});

it('menyalin pindahan berstatus Pindahan dan menolak yang belum lulus atau emailnya terpakai', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $cmb = calonMabaLulus(['status_masuk' => 'P', 'asal_perguruan_tinggi' => 'Univ Lama', 'nim_asal' => 'L-01', 'sks_diakui' => 40, 'jenjang_asal' => 'E']);

    $this->actingAs($admin)->post(route('admin.pendaftar-pmb.salin', $cmb), ['nim' => '2701P001'])->assertSessionHas('success');
    $profil = $cmb->mahasiswa()->firstOrFail();
    expect($profil)->status->toBe('Pindahan')->asal_perguruan_tinggi->toBe('Univ Lama')->sks_diakui->toBe(40)->sekolah_asal->toBeNull()
        ->and(MahasiswaProfile::STATUS_BOLEH_MASUK)->toContain('Pindahan');

    $menunggu = calonMabaLulus(['nik' => '7371010101080005', 'email' => 'lain@example.com']);
    $menunggu->forceFill(['status_pendaftaran' => null])->save();
    $this->actingAs($admin)->post(route('admin.pendaftar-pmb.salin', $menunggu), ['nim' => '2701A002'])->assertSessionHas('error');
    // NIM yang sudah dipakai ditolak.
    $menunggu->forceFill(['status_pendaftaran' => Cmb::STATUS_DITERIMA])->save();
    $this->actingAs($admin)->post(route('admin.pendaftar-pmb.salin', $menunggu), ['nim' => '2701P001'])->assertSessionHasErrors('nim');
    $menunggu->forceFill(['status_pendaftaran' => null])->save();

    User::factory()->create(['email' => 'dipakai@example.com']);
    $bentrok = calonMabaLulus(['nik' => '7371010101080006', 'email' => 'dipakai@example.com']);
    $this->actingAs($admin)->post(route('admin.pendaftar-pmb.salin', $bentrok), ['nim' => '2701A003'])->assertSessionHas('error');
    expect($menunggu->mahasiswa()->exists())->toBeFalse()->and($bentrok->mahasiswa()->exists())->toBeFalse();
});

it('mengubah nilai dan status calon maba dari tabel dan membatasi salin tanpa izin data mahasiswa', function () {
    $admin = User::factory()->admin()->create();
    $cmb = calonMabaLulus();

    $this->actingAs($admin)->put(route('admin.pendaftar-pmb.update', $cmb), ['nilai' => '55', 'status_pendaftaran' => 'ditolak'])->assertSessionHasNoErrors();
    expect($cmb->fresh())->nilai->toBe(55.0)->status_pendaftaran->toBe('ditolak');

    $role = Role::create(['name' => 'Panitia PMB', 'slug' => 'panitia-pmb', 'user_type' => UserType::Admin, 'is_system' => false]);
    $role->permissions()->sync(Permission::query()->where('key', 'admin.pendaftar-pmb')->pluck('id'));
    $panitia = User::factory()->admin()->create(['role_id' => $role->id]);
    $cmb->forceFill(['status_pendaftaran' => 'diterima'])->save();

    $this->actingAs($panitia)->get(route('admin.pendaftar-pmb.index'))->assertInertia(fn (Assert $page) => $page->where('bolehSalin', false));
    $this->actingAs($panitia)->post(route('admin.pendaftar-pmb.salin', $cmb), ['nim' => '2701A004'])->assertForbidden();
});

it('mahasiswa tanpa dosen wali dan data orang tua bisa disimpan dari form admin', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $cmb = calonMabaLulus();
    $this->actingAs($admin)->post(route('admin.pendaftar-pmb.salin', $cmb), ['nim' => '2701A005']);
    $user = $cmb->mahasiswa()->firstOrFail()->user;

    $this->actingAs($admin)->get(route('admin.users.mahasiswa.edit', $user))
        ->assertInertia(fn (Assert $page) => $page->where('user.kecamatan_label', $cmb->kecamatan->nama)->missing('user.berkas_ijazah')->has('opsi.kelas'));

    $this->actingAs($admin)->put(route('admin.users.mahasiswa.update', $user), [
        'role_id' => $user->role_id, 'name' => $user->name, 'username' => $user->username, 'email' => $user->email,
        'tempat_lahir' => 'Makassar', 'tanggal_lahir' => '2008-05-17', 'jenis_kelamin' => 'Laki-laki', 'agama' => 'Lainnya',
        'no_telepon' => '0812', 'alamat' => 'Jl. A', 'kewarganegaraan' => 'Indonesia', 'angkatan' => 2027, 'status' => 'Pindahan',
        'prodi_id' => $cmb->program_studi_id, 'nama_ibu_kandung' => 'Siti', 'jalur_kelas' => 'K', 'penerima_kps' => '0', 'nik' => '123',
    ])->assertSessionHasErrors('nik');

    $this->actingAs($admin)->put(route('admin.users.mahasiswa.update', $user), [
        'role_id' => $user->role_id, 'name' => $user->name, 'username' => $user->username, 'email' => $user->email,
        'tempat_lahir' => 'Makassar', 'tanggal_lahir' => '2008-05-17', 'jenis_kelamin' => 'Laki-laki', 'agama' => 'Lainnya',
        'no_telepon' => '0812', 'alamat' => 'Jl. A', 'kewarganegaraan' => 'Indonesia', 'angkatan' => 2027, 'status' => 'Pindahan',
        'prodi_id' => $cmb->program_studi_id, 'nama_ibu_kandung' => 'Siti', 'jalur_kelas' => 'K', 'penerima_kps' => '0',
    ])->assertSessionHasNoErrors();

    expect($user->mahasiswaProfile->fresh())->nim->toBe('2701A005')->status->toBe('Pindahan')->jalur_kelas->toBe('K')->agama->toBe('Lainnya');
});
