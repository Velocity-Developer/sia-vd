<?php

use App\Models\JenisBiaya;
use App\Models\Krs;
use App\Models\PengaturanAkademik;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Mahasiswa aktif yang sudah mengambil satu kelas, beserta kelas dan tahun akademiknya.
 */
function keuanganSetup(int $sks = 4): array
{
    $kelas = createMateriKelasKuliah();
    $kelas->mataKuliah->update(['sks' => $sks]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    $mahasiswa->mahasiswaProfile->update([
        'prodi_id' => $kelas->mataKuliah->prodi_id,
        'semester' => $kelas->mataKuliah->semester,
        'angkatan' => 2024,
        'status' => 'Aktif',
    ]);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

    return [$mahasiswa->fresh(), $kelas->fresh()];
}

function jenisBiayaContoh(?int $prodiId = null): array
{
    $tetap = JenisBiaya::create(['kode' => 'SPP-TETAP', 'nama' => 'SPP Tetap', 'cara_hitung' => JenisBiaya::TETAP, 'aktif' => true, 'urutan' => 1]);
    $tetap->tarif()->create(['prodi_id' => $prodiId, 'angkatan' => null, 'nominal' => 2_000_000]);

    $perSks = JenisBiaya::create(['kode' => 'SPP-SKS', 'nama' => 'SPP per SKS', 'cara_hitung' => JenisBiaya::PER_SKS, 'aktif' => true, 'urutan' => 2]);
    $perSks->tarif()->create(['prodi_id' => $prodiId, 'angkatan' => null, 'nominal' => 100_000]);

    return [$tetap, $perSks];
}

it('menutup halaman keuangan dari mahasiswa dan dosen', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();
    $dosen = User::factory()->dosen()->create();

    $this->actingAs($mahasiswa)->get(route('admin.tagihan.index'))->assertForbidden();
    $this->actingAs($mahasiswa)->get(route('admin.jenis-biaya.index'))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.tagihan.index'))->assertForbidden();
});

it('menyimpan jenis biaya beserta tarifnya', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.jenis-biaya.store'), [
        'kode' => 'SPP-TETAP',
        'nama' => 'SPP Tetap',
        'cara_hitung' => JenisBiaya::TETAP,
        'aktif' => true,
        'urutan' => 1,
        'tarif' => [
            ['prodi_id' => null, 'angkatan' => null, 'nominal' => 3_000_000],
            ['prodi_id' => null, 'angkatan' => 2024, 'nominal' => 2_500_000],
        ],
    ])->assertSessionHasNoErrors();

    $jenis = JenisBiaya::query()->with('tarif')->firstOrFail();

    expect($jenis->tarif)->toHaveCount(2)
        ->and($jenis->tarifUntuk(null, 2024)->nominal)->toBe(2_500_000)
        ->and($jenis->tarifUntuk(null, 2023)->nominal)->toBe(3_000_000);
});

it('memakai tarif yang paling khusus', function () {
    [, $kelas] = keuanganSetup();
    $prodiId = $kelas->mataKuliah->prodi_id;

    $jenis = JenisBiaya::create(['kode' => 'SPP', 'nama' => 'SPP', 'cara_hitung' => JenisBiaya::TETAP, 'aktif' => true]);
    $jenis->tarif()->createMany([
        ['prodi_id' => null, 'angkatan' => null, 'nominal' => 1_000_000],
        ['prodi_id' => $prodiId, 'angkatan' => null, 'nominal' => 2_000_000],
        ['prodi_id' => $prodiId, 'angkatan' => 2024, 'nominal' => 3_000_000],
    ]);

    $jenis->load('tarif');

    expect($jenis->tarifUntuk($prodiId, 2024)->nominal)->toBe(3_000_000)
        ->and($jenis->tarifUntuk($prodiId, 2023)->nominal)->toBe(2_000_000)
        ->and($jenis->tarifUntuk(999, 2023)->nominal)->toBe(1_000_000);
});

it('menerbitkan tagihan dengan biaya tetap dan biaya per kuota sks', function () {
    [$mahasiswa, $kelas] = keuanganSetup(sks: 4);
    jenisBiayaContoh($kelas->mataKuliah->prodi_id);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tagihan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id])
        ->assertSessionHasNoErrors();

    $tagihan = TagihanSemester::query()->with('items')->firstOrFail();

    // Tagihan terbit sebelum KRS, jadi pengalinya kuota SKS (tanpa IPS = 20), bukan 4 SKS yang diambil.
    // 2.000.000 tetap + (20 SKS x 100.000)
    expect($tagihan->total)->toBe(4_000_000)
        ->and($tagihan->status)->toBe(TagihanSemester::BELUM_BAYAR)
        ->and($tagihan->mahasiswa_id)->toBe($mahasiswa->mahasiswaProfile->id)
        ->and($tagihan->items->firstWhere('cara_hitung', JenisBiaya::PER_SKS)->jumlah)->toBe(20);
});

it('mengikuti batas SKS yang diatur admin saat menghitung biaya per sks', function () {
    [, $kelas] = keuanganSetup();
    jenisBiayaContoh($kelas->mataKuliah->prodi_id);
    PengaturanAkademik::current()->update(['maks_sks_tanpa_ips' => 12]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tagihan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);

    // 2.000.000 tetap + (12 SKS x 100.000)
    expect(TagihanSemester::query()->value('total'))->toBe(3_200_000);
});

it('tidak mengubah tagihan yang sudah lunas saat diterbitkan ulang', function () {
    [$mahasiswa, $kelas] = keuanganSetup();
    jenisBiayaContoh($kelas->mataKuliah->prodi_id);
    $admin = User::factory()->admin()->create();

    $tagihan = TagihanSemester::create([
        'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id,
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'status' => TagihanSemester::LUNAS,
        'total' => 123_000,
    ]);

    $this->actingAs($admin)->post(route('admin.tagihan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);

    expect($tagihan->fresh()->total)->toBe(123_000)
        ->and($tagihan->fresh()->status)->toBe(TagihanSemester::LUNAS);
});

it('menolak menerbitkan tagihan saat belum ada jenis biaya aktif', function () {
    [, $kelas] = keuanganSetup();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tagihan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id])
        ->assertSessionHas('error');

    expect(TagihanSemester::count())->toBe(0);
});

it('mengubah status pembayaran dan mencatat siapa yang mengubah', function () {
    [$mahasiswa, $kelas] = keuanganSetup();
    $admin = User::factory()->admin()->create();
    $profil = $mahasiswa->mahasiswaProfile;

    $this->actingAs($admin)->put(route('admin.tagihan.status'), [
        'mahasiswa_id' => $profil->id,
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'status' => TagihanSemester::LUNAS,
    ])->assertSessionHasNoErrors();

    $tagihan = TagihanSemester::query()->firstOrFail();

    expect($tagihan->status)->toBe(TagihanSemester::LUNAS)
        ->and($tagihan->tanggal_lunas)->not->toBeNull()
        ->and($tagihan->diubah_oleh)->toBe($admin->id);

    $this->actingAs($admin)->put(route('admin.tagihan.status'), [
        'mahasiswa_id' => $profil->id,
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'status' => TagihanSemester::BELUM_BAYAR,
    ]);

    expect(TagihanSemester::count())->toBe(1)
        ->and(TagihanSemester::query()->first()->tanggal_lunas)->toBeNull();
});

it('menyaring daftar tagihan dan menghitung ringkasannya', function () {
    [$mahasiswa, $kelas] = keuanganSetup();
    $lain = User::factory()->mahasiswa()->create();
    $lain->mahasiswaProfile->update(['status' => 'Aktif', 'angkatan' => 2023, 'prodi_id' => $kelas->mataKuliah->prodi_id]);
    $admin = User::factory()->admin()->create();

    TagihanSemester::create([
        'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id,
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'status' => TagihanSemester::LUNAS,
        'total' => 500_000,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tagihan.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->where('ringkasan.total', 2)->where('ringkasan.lunas', 1)->where('ringkasan.belum_bayar', 1));

    $this->actingAs($admin)
        ->get(route('admin.tagihan.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'status' => 'lunas']))
        ->assertInertia(fn ($page) => $page->has('daftar.data', 1)->where('daftar.data.0.nim', $mahasiswa->mahasiswaProfile->nim));

    $this->actingAs($admin)
        ->get(route('admin.tagihan.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'angkatan' => 2023]))
        ->assertInertia(fn ($page) => $page->has('daftar.data', 1)->where('daftar.data.0.status', TagihanSemester::BELUM_BAYAR));
});

it('tidak menampilkan mahasiswa yang tidak aktif', function () {
    [$mahasiswa, $kelas] = keuanganSetup();
    $mahasiswa->mahasiswaProfile->update(['status' => 'Cuti']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.tagihan.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->has('daftar.data', 0)->where('ringkasan.total', 0));
});

it('menyimpan satu tagihan saja per mahasiswa per semester', function () {
    [$mahasiswa, $kelas] = keuanganSetup();
    $profil = $mahasiswa->mahasiswaProfile;

    TagihanSemester::create(['mahasiswa_id' => $profil->id, 'tahun_akademik_id' => $kelas->tahun_akademik_id]);

    expect(fn () => TagihanSemester::create(['mahasiswa_id' => $profil->id, 'tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->toThrow(QueryException::class);
});

it('menampilkan tagihan mahasiswa yang sedang masuk saja', function () {
    [$mahasiswa, $kelas] = keuanganSetup();
    jenisBiayaContoh($kelas->mataKuliah->prodi_id);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.tagihan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);

    $lain = User::factory()->mahasiswa()->create();
    $lain->mahasiswaProfile->update(['status' => 'Aktif']);
    TagihanSemester::create([
        'mahasiswa_id' => $lain->mahasiswaProfile->id,
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'total' => 999_000,
    ]);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.info-biaya-kuliah'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('semesterBerjalan.total', 4_000_000)->has('riwayat', 0));
});

it('menolak mahasiswa lain membuka halaman admin keuangan', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();

    $this->actingAs($mahasiswa)->post(route('admin.tagihan.terbitkan'), ['tahun_akademik_id' => 1])->assertForbidden();
});

it('menghitung sks hanya dari krs aktif pada tahun akademik itu', function () {
    [$mahasiswa, $kelas] = keuanganSetup(sks: 3);
    $profil = $mahasiswa->mahasiswaProfile;

    expect(TagihanSemester::sksDiambil($profil->id, $kelas->tahun_akademik_id))->toBe(3);

    Krs::query()->update(['status' => 'Batal']);

    expect(TagihanSemester::sksDiambil($profil->id, $kelas->tahun_akademik_id))->toBe(0);
});

it('memperingatkan admin bila nilai semester sebelumnya belum lengkap', function () {
    [$mahasiswa, $kelas] = keuanganSetup();
    jenisBiayaContoh($kelas->mataKuliah->prodi_id);
    $admin = User::factory()->admin()->create();

    // Semester sebelumnya dengan satu KRS yang nilainya masih kosong.
    $tahunLalu = TahunAkademik::create([
        'tahun' => '2024/2025',
        'semester' => 'Ganjil',
        // Harus benar-benar lebih awal dari tahun akademik berjalan pada data uji (2025-08-01).
        'tanggal_mulai' => '2024-08-01',
        'tanggal_akhir' => '2025-01-31',
        'status' => false,
    ]);
    $kelasLalu = kelasLainDiProdi($kelas, 3, $tahunLalu, $kelas->mataKuliah->semester);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasLalu->id, 'status' => 'Aktif', 'nilai' => null]);

    $this->actingAs($admin)->post(route('admin.tagihan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id])
        ->assertSessionHas('tagihan_konfirmasi', fn (string $pesan): bool => str_contains($pesan, 'belum diisi'));

    expect(TagihanSemester::count())->toBe(0);

    $this->actingAs($admin)->post(route('admin.tagihan.terbitkan'), [
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'paksa' => true,
    ])->assertSessionHas('success');

    expect(TagihanSemester::count())->toBe(1);
});
