<?php

use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\PengaturanAkademik;
use App\Models\TagihanSemester;
use App\Models\User;

/**
 * Mahasiswa aktif + kelas di tahun akademik aktif yang periode KRS-nya sedang berjalan.
 */
function kunciKrsSetup(int $sks = 4): array
{
    $kelas = createMateriKelasKuliah();
    $kelas->tahunAkademik->update(['tanggal_krs_awal' => now()->subDay(), 'tanggal_krs_akhir' => now()->addDay()]);
    $kelas->mataKuliah->update(['sks' => $sks]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    $mahasiswa->mahasiswaProfile->update([
        'prodi_id' => $kelas->mataKuliah->prodi_id,
        'semester' => $kelas->mataKuliah->semester,
        'status' => 'Aktif',
    ]);

    return [$mahasiswa->fresh(), $kelas->fresh()];
}

function tagihanUntuk(User $mahasiswa, int $tahunAkademikId, string $status = TagihanSemester::BELUM_BAYAR): TagihanSemester
{
    return TagihanSemester::create([
        'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id,
        'tahun_akademik_id' => $tahunAkademikId,
        'status' => $status,
        'total' => 3_000_000,
    ]);
}

function nyalakanKunciKrs(): void
{
    PengaturanAkademik::current()->update(['kunci_krs_aktif' => true]);
}

it('membiarkan KRS terbuka selama sakelar penguncian mati', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup();
    tagihanUntuk($mahasiswa, $kelas->tahun_akademik_id);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/Krs'));
});

it('mengunci halaman KRS saat tagihan semester berjalan belum lunas', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup();
    tagihanUntuk($mahasiswa, $kelas->tahun_akademik_id);
    nyalakanKunciKrs();

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/KrsTerkunci')->where('total', 3_000_000));

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))
        ->assertSessionHas('krs_error', 'Tagihan semester ini belum lunas, pengisian KRS masih terkunci.');

    expect(Krs::count())->toBe(0);
});

it('membuka KRS begitu tagihan ditandai lunas', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup();
    $tagihan = tagihanUntuk($mahasiswa, $kelas->tahun_akademik_id);
    nyalakanKunciKrs();

    $tagihan->update(['status' => TagihanSemester::LUNAS]);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/Krs'));

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))->assertSessionHas('krs_success');
});

it('tidak mengunci mahasiswa yang tagihannya belum diterbitkan', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup();
    nyalakanKunciKrs();

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/Krs'));

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas))->assertSessionHas('krs_success');
});

it('meminta konfirmasi bila SKS yang diambil di bawah batas', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup(sks: 4);
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas));

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.simpan'))
        ->assertSessionHas('krs_konfirmasi', fn (string $pesan): bool => str_contains($pesan, '4 dari 20 SKS'));

    expect(KrsSemester::count())->toBe(0);

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.simpan'), ['konfirmasi' => true])
        ->assertSessionHas('krs_success');

    expect(KrsSemester::count())->toBe(1);
});

it('mengirim pesan konfirmasi ke halaman KRS', function () {
    // Pesan konfirmasi harus ikut daftar flash yang dibagikan ke Inertia; tanpa itu dialognya
    // tidak pernah muncul di browser walau server sudah mengirimnya.
    [$mahasiswa, $kelas] = kunciKrsSetup();
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas));
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.simpan'));

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertInertia(fn ($page) => $page->where('flash.krs_konfirmasi', fn (?string $pesan): bool => str_contains((string) $pesan, 'SKS')));
});

it('menolak menyimpan KRS yang masih kosong', function () {
    [$mahasiswa] = kunciKrsSetup();

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.simpan'), ['konfirmasi' => true])
        ->assertSessionHas('krs_error', 'Ambil minimal satu kelas sebelum menyimpan KRS.');

    expect(KrsSemester::count())->toBe(0);
});

it('mengunci penambahan dan pembatalan kelas setelah KRS disimpan', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup();
    $lain = kelasLainDiProdi($kelas, 3, semester: $kelas->mataKuliah->semester);

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas));
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.simpan'), ['konfirmasi' => true]);

    $krs = Krs::query()->firstOrFail();

    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $lain))
        ->assertSessionHas('krs_error', fn (string $pesan): bool => str_contains($pesan, 'sudah disimpan dan terkunci'));

    $this->actingAs($mahasiswa)->delete(route('mahasiswa.krs.destroy', $krs))
        ->assertSessionHas('krs_error', fn (string $pesan): bool => str_contains($pesan, 'terkunci'));

    expect(Krs::count())->toBe(1);
});

it('menganggap KRS terkunci setelah periode berakhir walau belum disimpan', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup();
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas));

    $kelas->tahunAkademik->update(['tanggal_krs_awal' => now()->subDays(10), 'tanggal_krs_akhir' => now()->subDay()]);

    expect(KrsSemester::terkunci($mahasiswa->mahasiswaProfile->id, $kelas->tahunAkademik->fresh()))->toBeTrue();
});

it('memungkinkan admin membuka kunci KRS mahasiswa', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup();
    $admin = User::factory()->admin()->create();
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.store', $kelas));
    $this->actingAs($mahasiswa)->post(route('mahasiswa.krs.simpan'), ['konfirmasi' => true]);

    $this->actingAs($admin)->delete(route('admin.tagihan.buka-kunci-krs', $mahasiswa->mahasiswaProfile->id), [
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
    ])->assertSessionHas('success');

    expect(KrsSemester::count())->toBe(0);

    $krs = Krs::query()->firstOrFail();
    $this->actingAs($mahasiswa)->delete(route('mahasiswa.krs.destroy', $krs))->assertSessionHas('krs_success');
});

it('menolak mahasiswa membuka kunci KRS sendiri', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup();

    $this->actingAs($mahasiswa)->delete(route('admin.tagihan.buka-kunci-krs', $mahasiswa->mahasiswaProfile->id), [
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
    ])->assertForbidden();
});

it('menyimpan rincian tagihan yang diketik admin dan menghitung ulang totalnya', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.tagihan.rincian.simpan', $mahasiswa->mahasiswaProfile->id), [
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'items' => [
            ['nama' => 'SPP Tetap', 'subtotal' => 2_500_000],
            ['nama' => 'Praktikum', 'subtotal' => 500_000],
        ],
    ])->assertSessionHasNoErrors();

    $tagihan = TagihanSemester::query()->with('items')->firstOrFail();

    expect($tagihan->total)->toBe(3_000_000)
        ->and($tagihan->items)->toHaveCount(2)
        ->and($tagihan->diubah_oleh)->toBe($admin->id);
});

it('tidak mengunci halaman lain saat tagihan belum lunas', function () {
    [$mahasiswa, $kelas] = kunciKrsSetup();
    tagihanUntuk($mahasiswa, $kelas->tahun_akademik_id);
    nyalakanKunciKrs();

    $this->actingAs($mahasiswa)->get(route('mahasiswa.info-biaya-kuliah'))->assertOk();
    $this->actingAs($mahasiswa)->get(route('mahasiswa.khs'))->assertOk();
    $this->actingAs($mahasiswa)->get(route('mahasiswa.jadwal-kuliah'))->assertOk();
});
