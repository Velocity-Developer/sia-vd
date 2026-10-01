<?php

use App\Models\Agama;
use App\Models\Cmb;
use App\Models\PengaturanPmb;
use App\Models\PengaturanRecaptcha;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Models\WilayahKecamatan;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

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

    $this->post(route('pmb.daftar.store'), isianPmb(['pengaturan_pmb_id' => $lain->id, 'nilai' => 99, 'status_pendaftaran' => 'lulus']));

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
        ->assertInertia(fn (Assert $page) => $page->component('Admin/PendaftarPmbShow')->where('pendaftar.kecamatan.kode', $cmb->kecamatan->kode));

    $this->actingAs($admin)->put(route('admin.pendaftar-pmb.update', $cmb), ['nilai' => 75.5, 'status_pendaftaran' => 'lulus'])->assertSessionHasNoErrors();
    expect($cmb->fresh())->nilai->toBe(75.5)->status_pendaftaran->toBe('lulus');

    $this->actingAs($admin)->put(route('admin.pendaftar-pmb.update', $cmb), ['nilai' => 120, 'status_pendaftaran' => 'diterima'])
        ->assertSessionHasErrors(['nilai', 'status_pendaftaran']);

    $this->actingAs($admin)->get(route('admin.pendaftar-pmb.index', ['status' => 'lulus']))
        ->assertInertia(fn (Assert $page) => $page->where('pendaftar.total', 1));

    // Periode yang sudah punya pendaftar tidak bisa dihapus.
    $this->actingAs($admin)->delete(route('admin.periode-pmb.destroy', $periode))->assertSessionHas('error');

    $this->actingAs($admin)->delete(route('admin.pendaftar-pmb.destroy', $cmb))->assertRedirect(route('admin.pendaftar-pmb.index'));
    expect(Cmb::query()->count())->toBe(0);
});

it('membatasi data pendaftar untuk pemegang izin', function () {
    $this->actingAs(User::factory()->mahasiswa()->create())->get(route('admin.pendaftar-pmb.index'))->assertForbidden();
});
