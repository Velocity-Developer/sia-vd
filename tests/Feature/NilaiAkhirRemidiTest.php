<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengaturanAkademik;
use App\Models\RemidiPeserta;
use App\Models\TagihanRemidi;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\User;

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
