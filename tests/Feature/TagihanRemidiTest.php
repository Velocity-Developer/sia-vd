<?php

use App\Models\JenisBiaya;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\RemidiPeserta;
use App\Models\TagihanRemidi;
use App\Models\TagihanSemester;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Kelas final dengan dua mahasiswa bernilai E, daftar remidi sudah dikunci; batas bayar besok.
 *
 * @return array{0: KelasKuliah, 1: list<User>}
 */
function kelasTagihanRemidi(bool $kunci = true): array
{
    $kelas = createMateriKelasKuliah();
    $mhs = [];
    foreach ([1, 2] as $_) {
        $mhs[] = $user = User::factory()->mahasiswa()->create();
        Krs::create(['mahasiswa_id' => $user->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'E']);
        RemidiPeserta::create(['kelas_id' => $kelas->id, 'mahasiswa_id' => $user->mahasiswaProfile->id, 'nilai_awal' => 'E', 'diusulkan' => true]);
    }
    $kelas->update(['nilai_final_at' => now(), 'remidi_dikunci_at' => $kunci ? now() : null]);
    $kelas->tahunAkademik->update(['batas_bayar_remidi' => now()->addDay()->toDateString()]);

    return [$kelas->fresh(), $mhs];
}

function jenisBiayaRemidi(int $nominal = 50_000, string $cara = JenisBiaya::PER_SKS): JenisBiaya
{
    $jenis = JenisBiaya::create(['kode' => 'REMIDI-'.$cara.'-'.$nominal, 'nama' => 'Biaya Remidi', 'cara_hitung' => $cara, 'kategori' => JenisBiaya::REMIDI, 'aktif' => true]);
    $jenis->tarif()->create(['prodi_id' => null, 'angkatan' => null, 'nominal' => $nominal]);

    return $jenis;
}

function terbitkanRemidi($test, KelasKuliah $kelas)
{
    return $test->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tagihan-remidi.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);
}

it('issues remidi bills from locked lists only, priced per SKS of the course', function () {
    [$kelas, $mhs] = kelasTagihanRemidi();
    [$belumKunci] = kelasTagihanRemidi(kunci: false);
    jenisBiayaRemidi();
    $sks = $kelas->mataKuliah->sks;

    terbitkanRemidi($this, $kelas)->assertSessionHas('success', '2 tagihan remidi diterbitkan.');

    expect(TagihanRemidi::where('kelas_id', $belumKunci->id)->count())->toBe(0);
    $tagihan = TagihanRemidi::where('mahasiswa_id', $mhs[0]->mahasiswaProfile->id)->first();
    expect($tagihan->total)->toBe(50_000 * $sks)
        ->and($tagihan->status)->toBe(TagihanRemidi::BELUM_BAYAR)
        ->and($tagihan->rincian[0]['jumlah'])->toBe($sks);

    // Menerbitkan lagi tidak menggandakan.
    terbitkanRemidi($this, $kelas)->assertSessionHas('success', 'Tidak ada peserta baru yang perlu ditagih.');
    expect(TagihanRemidi::count())->toBe(2);
});

it('refuses to issue without a deadline, after the deadline, or without a remidi fee', function () {
    [$kelas] = kelasTagihanRemidi();

    terbitkanRemidi($this, $kelas)->assertSessionHas('error');
    jenisBiayaRemidi();

    $kelas->tahunAkademik->update(['batas_bayar_remidi' => null]);
    terbitkanRemidi($this, $kelas)->assertSessionHas('error', 'Isi dulu Batas Bayar Remidi di menu Tahun Akademik.');

    $kelas->tahunAkademik->update(['batas_bayar_remidi' => now()->subDay()->toDateString()]);
    terbitkanRemidi($this, $kelas)->assertSessionHas('error');

    expect(TagihanRemidi::count())->toBe(0);
});

it('marks a zero-priced bill as paid and keeps remidi fees out of semester bills', function () {
    [$kelas, $mhs] = kelasTagihanRemidi();
    jenisBiayaRemidi(0, JenisBiaya::TETAP);

    terbitkanRemidi($this, $kelas);
    expect(TagihanRemidi::where('mahasiswa_id', $mhs[0]->mahasiswaProfile->id)->value('status'))->toBe(TagihanRemidi::LUNAS);

    jenisBiayaRemidi(75_000, JenisBiaya::TETAP);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.tagihan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'paksa' => true])
        ->assertSessionHas('error', 'Belum ada jenis biaya aktif. Isi dulu di menu Jenis Biaya.');

    $spp = JenisBiaya::create(['kode' => 'SPP', 'nama' => 'SPP', 'cara_hitung' => JenisBiaya::TETAP, 'aktif' => true]);
    $spp->tarif()->create(['nominal' => 1_000_000]);
    $this->actingAs($admin)->post(route('admin.tagihan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'paksa' => true]);
    expect(TagihanSemester::where('mahasiswa_id', $mhs[0]->mahasiswaProfile->id)->value('total'))->toBe(1_000_000);
});

it('runs the upload, reject, re-upload, and verify cycle', function () {
    Storage::fake('local');
    [$kelas, $mhs] = kelasTagihanRemidi();
    jenisBiayaRemidi();
    terbitkanRemidi($this, $kelas);
    $tagihan = TagihanRemidi::where('mahasiswa_id', $mhs[0]->mahasiswaProfile->id)->first();
    $admin = User::factory()->admin()->create();
    $bukti = fn () => ['bukti' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')];

    // Tagihan orang lain tidak bisa disentuh.
    $this->actingAs($mhs[1])->post(route('mahasiswa.tagihan-remidi.bukti', $tagihan), $bukti())->assertNotFound();
    $this->actingAs($mhs[0])->post(route('mahasiswa.tagihan-remidi.bukti', $tagihan), ['bukti' => UploadedFile::fake()->create('x.html', 5, 'text/html')])
        ->assertSessionHasErrors('bukti');

    $this->actingAs($mhs[0])->post(route('mahasiswa.tagihan-remidi.bukti', $tagihan), $bukti())->assertSessionHas('success');
    $tagihan->refresh();
    expect($tagihan->status)->toBe(TagihanRemidi::MENUNGGU);
    $pertama = $tagihan->bukti;
    Storage::disk('local')->assertExists($pertama);

    $this->actingAs($admin)->post(route('admin.tagihan-remidi.tolak', $tagihan), ['alasan' => ''])->assertSessionHasErrors('alasan');
    $this->actingAs($admin)->post(route('admin.tagihan-remidi.tolak', $tagihan), ['alasan' => 'Nominal kurang'])->assertSessionHas('success');
    expect($tagihan->fresh()->status)->toBe(TagihanRemidi::DITOLAK)->and($tagihan->fresh()->alasan_tolak)->toBe('Nominal kurang');

    $this->actingAs($mhs[0])->post(route('mahasiswa.tagihan-remidi.bukti', $tagihan), $bukti())->assertSessionHas('success');
    Storage::disk('local')->assertMissing($pertama);
    expect($tagihan->fresh()->status)->toBe(TagihanRemidi::MENUNGGU)->and($tagihan->fresh()->alasan_tolak)->toBeNull();

    $this->actingAs($admin)->post(route('admin.tagihan-remidi.lunas', $tagihan))->assertSessionHas('success');
    expect($tagihan->fresh()->status)->toBe(TagihanRemidi::LUNAS)->and($tagihan->fresh()->diverifikasi_oleh)->toBe($admin->id);

    $this->actingAs($mhs[0])->post(route('mahasiswa.tagihan-remidi.bukti', $tagihan), $bukti())->assertSessionHas('error', 'Tagihan ini sudah lunas.');

    $this->actingAs($mhs[0])->get(route('mahasiswa.info-biaya-kuliah'))
        ->assertInertia(fn ($page) => $page->has('tagihanRemidi', 1)->where('tagihanRemidi.0.status', 'lunas')->where('tagihanRemidi.0.boleh_unggah', false));
});

it('treats unpaid bills as lapsed after the deadline, but still lets admin verify proof sent on time', function () {
    Storage::fake('local');
    [$kelas, $mhs] = kelasTagihanRemidi();
    jenisBiayaRemidi();
    terbitkanRemidi($this, $kelas);
    [$a, $b] = TagihanRemidi::orderBy('id')->get();
    $this->actingAs($mhs[0])->post(route('mahasiswa.tagihan-remidi.bukti', $a), ['bukti' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')]);

    $kelas->tahunAkademik->update(['batas_bayar_remidi' => now()->subDay()->toDateString()]);

    $this->actingAs($mhs[1])->post(route('mahasiswa.tagihan-remidi.bukti', $b), ['bukti' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])
        ->assertSessionHas('error', 'Batas bayar remidi sudah lewat.');
    $this->actingAs($mhs[1])->get(route('mahasiswa.info-biaya-kuliah'))->assertInertia(fn ($page) => $page->where('tagihanRemidi.0.status', 'gugur'));

    $this->actingAs(User::factory()->admin()->create())->post(route('admin.tagihan-remidi.lunas', $a->fresh()))->assertSessionHas('success');
    expect($a->fresh()->statusTampil())->toBe(TagihanRemidi::LUNAS)
        ->and($b->fresh()->statusTampil())->toBe(TagihanRemidi::GUGUR);
});

it('serves proof only to its owner and finance admins', function () {
    Storage::fake('local');
    [$kelas, $mhs] = kelasTagihanRemidi();
    jenisBiayaRemidi();
    terbitkanRemidi($this, $kelas);
    $tagihan = TagihanRemidi::where('mahasiswa_id', $mhs[0]->mahasiswaProfile->id)->first();
    $this->actingAs($mhs[0])->post(route('mahasiswa.tagihan-remidi.bukti', $tagihan), ['bukti' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')]);

    $this->actingAs($mhs[0])->get(route('berkas.bukti-remidi', $tagihan))->assertOk();
    $this->actingAs($mhs[1])->get(route('berkas.bukti-remidi', $tagihan))->assertForbidden();
    $this->actingAs($kelas->dosen->user)->get(route('berkas.bukti-remidi', $tagihan))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('berkas.bukti-remidi', $tagihan))->assertOk();
});

it('blocks reopening the remidi list once bills exist, and shows the admin page', function () {
    [$kelas] = kelasTagihanRemidi();
    jenisBiayaRemidi();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.tagihan-remidi.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->where('ringkasan.belum_ditagih', 2)->where('ringkasan.kelas_dikunci', 1));

    terbitkanRemidi($this, $kelas);
    $this->actingAs($admin)->post(route('admin.kelas-kuliah.remidi.buka', $kelas))->assertSessionHas('error');
    expect($kelas->fresh()->remidi_dikunci_at)->not->toBeNull();

    $this->actingAs($admin)->get(route('admin.tagihan-remidi.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'status' => 'belum_bayar']))
        ->assertInertia(fn ($page) => $page->has('tagihan.data', 2)->where('ringkasan.belum_bayar', 2)->where('ringkasan.belum_ditagih', 0));
    $this->actingAs(User::factory()->mahasiswa()->create())->get(route('admin.tagihan-remidi.index'))->assertForbidden();
});

it('lists final classes whose remidi list is not locked and locks them all with the automatic proposal', function () {
    [$sudah] = kelasTagihanRemidi();
    [$belum, $mhs] = kelasTagihanRemidi(kunci: false);
    RemidiPeserta::where('kelas_id', $belum->id)->delete();
    Krs::create(['mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id, 'kelas_id' => $belum->id, 'nilai' => 'A']);
    // Kelas belum final dan kelas tanpa mahasiswa tidak ikut.
    $belumFinal = createMateriKelasKuliah($sudah->tahunAkademik);
    Krs::create(['mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id, 'kelas_id' => $belumFinal->id, 'nilai' => 'E']);
    $kosong = createMateriKelasKuliah($sudah->tahunAkademik);
    $kosong->update(['nilai_final_at' => now()]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.tagihan-remidi.index', ['tahun_akademik_id' => $sudah->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->has('kelasBelumKunci', 1)->where('kelasBelumKunci.0.id', $belum->id));

    $this->actingAs($admin)->post(route('admin.tagihan-remidi.kunci-massal'), ['tahun_akademik_id' => $sudah->tahun_akademik_id])
        ->assertSessionHas('success', '1 daftar remidi dikunci memakai usulan otomatis (2 peserta).');

    expect($belum->fresh()->remidi_dikunci_oleh)->toBe($admin->id)
        ->and(RemidiPeserta::where('kelas_id', $belum->id)->pluck('mahasiswa_id')->sort()->values()->all())
        ->toBe(collect($mhs)->map(fn ($u) => $u->mahasiswaProfile->id)->sort()->values()->all())
        ->and($belumFinal->fresh()->remidi_dikunci_at)->toBeNull();
});
