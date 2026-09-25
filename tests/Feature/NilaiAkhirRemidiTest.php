<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengaturanAkademik;
use App\Models\RemidiPeserta;
use App\Models\TagihanRemidi;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Kelas final: [0] peserta lunas, [1] peserta belum bayar, [2] bukan peserta; semua bernilai E.
 * Ujian remidi berkas 15 Jan 2026 09:00–11:00, batas input nilai remidi 25 Jan 2026.
 *
 * @return array{0: KelasKuliah, 1: list<Krs>, 2: Ujian}
 */
function kelasNilaiRemidi(): array
{
    $kelas = createMateriKelasKuliah();
    $krs = [];
    foreach (range(0, 2) as $i) {
        $user = User::factory()->mahasiswa()->create();
        $krs[] = Krs::create(['mahasiswa_id' => $user->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'E']);

        if ($i < 2) {
            $peserta = RemidiPeserta::create(['kelas_id' => $kelas->id, 'mahasiswa_id' => $user->mahasiswaProfile->id, 'nilai_awal' => 'E', 'diusulkan' => true]);
            TagihanRemidi::create([
                'remidi_peserta_id' => $peserta->id, 'mahasiswa_id' => $user->mahasiswaProfile->id, 'kelas_id' => $kelas->id,
                'rincian' => [], 'total' => 100_000, 'status' => $i === 0 ? TagihanRemidi::LUNAS : TagihanRemidi::BELUM_BAYAR,
            ]);
        }
    }
    $kelas->update(['nilai_final_at' => '2026-01-05 10:00:00', 'remidi_dikunci_at' => '2026-01-06 10:00:00']);
    $kelas->tahunAkademik->update(['batas_bayar_remidi' => '2026-01-12', 'batas_input_nilai_remidi' => '2026-01-25']);
    $ujian = Ujian::create([
        'kelas_id' => $kelas->id, 'jenis' => 'remidi', 'mode' => 'online_berkas', 'tanggal' => '2026-01-15',
        'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'status' => 'terbit',
    ]);

    return [$kelas->fresh(), $krs, $ujian];
}

function ubahHuruf($test, KelasKuliah $kelas, Krs $krs, ?string $nilai, ?User $oleh = null)
{
    return $test->actingAs($oleh ?? $kelas->dosen->user)->put(route(($oleh ? 'admin' : 'dosen').'.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => $nilai]);
}

it('opens the final grade of paid remidi participants only after the remidi ends', function () {
    [$kelas, $krs] = kelasNilaiRemidi();

    $this->travelTo('2026-01-15 10:00:00');
    ubahHuruf($this, $kelas, $krs[0], 'C')->assertSessionHas('error');

    $this->travelTo('2026-01-15 12:00:00');
    ubahHuruf($this, $kelas, $krs[0], 'C')->assertSessionHas('success');
    expect($krs[0]->fresh()->nilai)->toBe('C');

    // Belum bayar dan bukan peserta tetap terkunci; huruf akhir peserta tidak boleh dikosongkan.
    ubahHuruf($this, $kelas, $krs[1], 'C')->assertSessionHas('error');
    ubahHuruf($this, $kelas, $krs[2], 'C')->assertSessionHas('error');
    ubahHuruf($this, $kelas, $krs[0], null)->assertSessionHasErrors('nilai');

    $this->actingAs($kelas->dosen->user)->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('remidiTerbuka', [$krs[0]->mahasiswa_id])->where('remidi.ujian.selesai', true));
});

it('caps the grade after remidi when a maximum letter is set', function () {
    [$kelas, $krs] = kelasNilaiRemidi();
    PengaturanAkademik::current()->update(['huruf_maks_remidi' => 'C']);
    $this->travelTo('2026-01-16 08:00:00');

    ubahHuruf($this, $kelas, $krs[0], 'B')->assertSessionHasErrors(['nilai' => 'Huruf akhir setelah remidi paling tinggi C.']);
    ubahHuruf($this, $kelas, $krs[0], 'D')->assertSessionHas('success');

    $this->actingAs($kelas->dosen->user)->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('hurufRemidi', ['C', 'D', 'E']));
});

it('locks again after the remidi deadline or when the lecturer finalizes, and admin can reopen', function () {
    [$kelas, $krs, $ujian] = kelasNilaiRemidi();
    $admin = User::factory()->admin()->create();
    UjianJawaban::create(['ujian_id' => $ujian->id, 'mahasiswa_id' => $krs[0]->mahasiswa_id, 'berkas' => ['a.pdf'], 'dikumpulkan_at' => '2026-01-15 10:00:00']);

    $this->travelTo('2026-01-15 10:30:00');
    $this->actingAs($kelas->dosen->user)->post(route('dosen.kelas-kuliah.remidi.finalisasi', $kelas))->assertSessionHas('error');

    $this->travelTo('2026-01-16 08:00:00');
    $this->actingAs($kelas->dosen->user)->post(route('dosen.kelas-kuliah.remidi.finalisasi', $kelas))->assertSessionHas('success');
    ubahHuruf($this, $kelas, $krs[0], 'C')->assertSessionHas('error');
    $this->actingAs($kelas->dosen->user)->put(route('dosen.ujian.nilai', [$ujian, $krs[0]->mahasiswa]), ['nilai' => 70])->assertForbidden();

    $this->actingAs($kelas->dosen->user)->post(route('admin.kelas-kuliah.remidi.buka-finalisasi', $kelas))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.kelas-kuliah.remidi.buka-finalisasi', $kelas))->assertSessionHas('success');
    $this->actingAs($kelas->dosen->user)->put(route('dosen.ujian.nilai', [$ujian, $krs[0]->mahasiswa]), ['nilai' => 70])->assertSessionHas('success');
    ubahHuruf($this, $kelas, $krs[0], 'C')->assertSessionHas('success');

    $baris = collect($this->actingAs($kelas->dosen->user)->get(route('dosen.kelas-kuliah.show', $kelas))->inertiaProps('remidi.mahasiswa'))
        ->keyBy('mahasiswa_id');
    expect($baris[$krs[0]->mahasiswa_id])->toMatchArray(['nilai_remidi' => 70.0, 'tagihan' => 'lunas', 'nilai_awal' => 'E'])
        ->and($baris[$krs[1]->mahasiswa_id]['tagihan'])->toBe('gugur');

    $this->travelTo('2026-01-26 08:00:00');
    ubahHuruf($this, $kelas, $krs[0], 'B')->assertSessionHas('error');
    ubahHuruf($this, $kelas, $krs[0], 'B', $admin)->assertSessionHas('success');
});

it('stores the maximum letter in the academic settings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.remidi'), ['huruf_maks_remidi' => 'Z'])->assertSessionHasErrors('huruf_maks_remidi');
    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.remidi'), ['huruf_maks_remidi' => 'C'])->assertSessionHas('success');
    expect(PengaturanAkademik::current()->huruf_maks_remidi)->toBe('C');

    // Huruf yang dihapus dari skala membuat batas kembali bebas.
    $skala = collect([['A', 4, true, false], ['B', 3, true, false], ['D', 1, true, true], ['E', 0, false, true]])
        ->map(fn ($r) => ['huruf' => $r[0], 'bobot' => $r[1], 'lulus' => $r[2], 'boleh_diulang' => $r[3]])->all();
    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.skala-nilai'), ['skala_nilai' => $skala])->assertSessionHas('success');
    expect(PengaturanAkademik::current()->fresh()->huruf_maks_remidi)->toBeNull();

    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.remidi'), ['huruf_maks_remidi' => null])->assertSessionHas('success');
});

it('keeps remidi participants locked when admin reopens the class grades', function () {
    [$kelas, $krs] = kelasNilaiRemidi();
    $admin = User::factory()->admin()->create();
    $this->travelTo('2026-01-14 08:00:00');
    $this->actingAs($admin)->post(route('admin.kelas-kuliah.buka-kunci-nilai', $kelas))->assertSessionHas('success');

    // Bukan peserta bisa diubah; peserta (lunas maupun belum) menunggu remidi.
    ubahHuruf($this, $kelas, $krs[2], 'D')->assertSessionHas('success');
    ubahHuruf($this, $kelas, $krs[0], 'C')->assertSessionHas('error', 'Huruf akhir peserta remidi hanya bisa diubah setelah ujian remidinya selesai.');
    ubahHuruf($this, $kelas, $krs[1], 'C')->assertSessionHas('error');
    $this->actingAs($kelas->dosen->user)->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('pesertaRemidi', fn ($ids) => collect($ids)->sort()->values()->all() === collect([$krs[0]->mahasiswa_id, $krs[1]->mahasiswa_id])->sort()->values()->all()));

    // Sesudah ujian remidi selesai, peserta lunas lewat jalur remidi (dengan batas huruf).
    PengaturanAkademik::current()->update(['huruf_maks_remidi' => 'C']);
    $this->travelTo('2026-01-16 08:00:00');
    ubahHuruf($this, $kelas, $krs[0], 'B')->assertSessionHasErrors('nilai');
    ubahHuruf($this, $kelas, $krs[0], 'C')->assertSessionHas('success');
    ubahHuruf($this, $kelas, $krs[1], 'C')->assertSessionHas('error');
    // Admin tidak dibatasi.
    ubahHuruf($this, $kelas, $krs[1], 'B', $admin)->assertSessionHas('success');
});

it('loads each published exam (UAS, remidi) once when showing a class', function () {
    [$kelas] = kelasNilaiRemidi();
    $this->travelTo('2026-01-16 08:00:00');
    $dosen = $kelas->dosen->user;
    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.show', $kelas));

    DB::enableQueryLog();
    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.show', $kelas))->assertOk();
    $kueri = collect(DB::getQueryLog())->pluck('query');

    expect($kueri->filter(fn ($q) => str_contains($q, 'from "ujians"') && str_contains($q, '"jenis" = ?'))->count())->toBe(2)
        ->and($kueri->filter(fn ($q) => str_contains($q, 'from "users" where "users"."id" is null'))->count())->toBe(0)
        ->and($kueri->filter(fn ($q) => str_contains($q, 'from "pengaturan_akademik"'))->count())->toBe(1);
});

it('warns about pending payment proofs when scheduling and on the bills page', function () {
    [$kelas, $krs] = kelasNilaiRemidi();
    TagihanRemidi::where('mahasiswa_id', $krs[1]->mahasiswa_id)->update(['status' => TagihanRemidi::MENUNGGU, 'bukti' => 'bukti-bayar/x.pdf']);
    $admin = User::factory()->admin()->create();
    $this->travelTo('2026-01-16 08:00:00');

    $this->actingAs($admin)->get(route('admin.ujian.create', ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'jenis' => 'remidi']))
        ->assertInertia(fn ($page) => $page->where("menungguVerifikasi.{$kelas->id}", 1));
    $this->actingAs($admin)->get(route('admin.tagihan-remidi.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'status' => 'menunggu_verifikasi']))
        ->assertInertia(fn ($page) => $page->where('tagihan.data.0.ujian_remidi.tanggal', '2026-01-15')->where('tagihan.data.0.ujian_remidi.lewat', true));
});

it('requires the remidi payment deadline to come after the grade deadline', function () {
    $admin = User::factory()->admin()->create();
    $isian = ['tahun' => '2031/2032', 'semester' => 'Ganjil', 'tanggal_mulai' => '2031-08-01', 'tanggal_akhir' => '2032-01-31',
        'tanggal_krs_awal' => '2031-08-01', 'tanggal_krs_akhir' => '2031-08-14', 'status' => false];

    $this->actingAs($admin)->post(route('admin.tahun-akademik.store'), [...$isian, 'batas_input_nilai' => '2032-01-20', 'batas_bayar_remidi' => '2032-01-20'])
        ->assertSessionHasErrors(['batas_bayar_remidi' => 'Batas bayar remidi harus setelah batas input nilai.']);
    // Batas input nilai remidi boleh diisi tanpa batas bayar (tidak gagal karena pembanding kosong).
    $this->actingAs($admin)->post(route('admin.tahun-akademik.store'), [...$isian, 'batas_input_nilai_remidi' => '2032-01-30'])->assertSessionHasNoErrors();
});

it('reminds students of unpaid remidi bills and upcoming remidi exams on the dashboard', function () {
    [$kelas, $krs] = kelasNilaiRemidi();
    $lunas = $krs[0]->mahasiswa->user;
    $belum = $krs[1]->mahasiswa->user;

    $this->travelTo('2026-01-10 08:00:00');
    $this->actingAs($belum)->get(route('mahasiswa.dashboard'))
        ->assertInertia(fn ($page) => $page->has('remidiMahasiswa.tagihan', 1)->where('remidiMahasiswa.tagihan.0.batas_bayar', '2026-01-12')->has('remidiMahasiswa.ujian', 0));
    $this->actingAs($lunas)->get(route('mahasiswa.dashboard'))
        ->assertInertia(fn ($page) => $page->has('remidiMahasiswa.tagihan', 0)->has('remidiMahasiswa.ujian', 1)->where('remidiMahasiswa.ujian.0.tanggal', '2026-01-15'));

    // Lewat batas bayar tagihan gugur tidak diingatkan lagi; sesudah ujian selesai jadwal hilang.
    $this->travelTo('2026-01-15 12:00:00');
    $this->actingAs($belum)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page->has('remidiMahasiswa.tagihan', 0));
    $this->actingAs($lunas)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page->has('remidiMahasiswa.ujian', 0));
});

it('reminds lecturers to lock remidi lists and to grade finished remidi', function () {
    [$kelas] = kelasNilaiRemidi();
    $dosen = $kelas->dosen->user;
    $lain = KelasKuliah::create(['kode_kelas' => 'LAIN-B', 'tahun_akademik_id' => $kelas->tahun_akademik_id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id, 'nilai_final_at' => now()]);
    Krs::create(['mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id, 'kelas_id' => $lain->id, 'nilai' => 'E']);

    $this->travelTo('2026-01-14 08:00:00');
    $this->actingAs($dosen)->get(route('dosen.dashboard'))
        ->assertInertia(fn ($page) => $page->where('remidiDosen.kunci_daftar.0.id', $lain->id)->has('remidiDosen.kunci_daftar', 1)->has('remidiDosen.isi_nilai', 0));

    $this->travelTo('2026-01-16 08:00:00');
    $this->actingAs($dosen)->get(route('dosen.dashboard'))
        ->assertInertia(fn ($page) => $page->where('remidiDosen.isi_nilai.0.id', $kelas->id));

    $kelas->update(['remidi_final_at' => now()]);
    $this->actingAs($dosen)->get(route('dosen.dashboard'))->assertInertia(fn ($page) => $page->has('remidiDosen.isi_nilai', 0));
});
