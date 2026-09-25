<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\Ujian;
use App\Models\User;

/**
 * @return array{0: KelasKuliah, 1: Krs, 2: User}
 */
function kelasBernilai(): array
{
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'C']);

    return [$kelas, $krs, $mahasiswa];
}

it('locks every grade in the class for dosen after finalization, but not for admin', function () {
    [$kelas, $krs, $mahasiswa] = kelasBernilai();
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_tugas' => 'Tugas']);
    $pengumpulan = PengumpulanTugas::create(['tugas_id' => $tugas->id, 'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'file_jawaban' => ['pengumpulan-tugas/a.pdf'], 'submitted_at' => now()]);
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('success');
    expect($kelas->fresh()->nilai_final_at)->not->toBeNull()
        ->and($kelas->fresh()->nilai_final_oleh)->toBe($dosen->id);

    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'A'])->assertSessionHas('error');
    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.tugas.pengumpulan.nilai', [$kelas, $tugas, $pengumpulan]), ['nilai' => 90])->assertForbidden();
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('error');
    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('nilaiTerkunci', true)->where('statusNilai.final', true)->where('statusNilai.final_oleh', $dosen->name));
    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.tugas.show', [$kelas, $tugas]))
        ->assertInertia(fn ($page) => $page->where('nilaiTerkunci', 'Nilai kelas ini sudah difinalisasi. Hubungi admin bila perlu dibuka kembali.'));
    expect($krs->fresh()->nilai)->toBe('C');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'B'])
        ->assertSessionHas('success');
    expect($krs->fresh()->nilai)->toBe('B');
});

it('refuses finalization until the published UAS has ended', function () {
    [$kelas] = kelasBernilai();
    $ujian = Ujian::create(['kelas_id' => $kelas->id, 'jenis' => 'uas', 'mode' => 'online_berkas', 'tanggal' => now()->addDay()->toDateString(), 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'status' => 'terbit']);
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.show', $kelas))->assertInertia(fn ($page) => $page->where('statusNilai.uas_belum_selesai', true));
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('error');
    expect($kelas->fresh()->nilai_final_at)->toBeNull();

    $ujian->update(['tanggal' => now()->subDay()->toDateString()]);
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('success');
});

it('locks grades after the tahun akademik grade deadline and lets admin reopen with a new deadline', function () {
    [$kelas, $krs] = kelasBernilai();
    $kelas->tahunAkademik->update(['batas_input_nilai' => now()->toDateString()]);
    $dosen = $kelas->dosen->user;
    $admin = User::factory()->admin()->create();

    // Hari batas masih boleh.
    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'B'])->assertSessionHas('success');

    $kelas->tahunAkademik->update(['batas_input_nilai' => now()->subDay()->toDateString()]);
    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'A'])
        ->assertSessionHas('error', 'Batas input nilai sudah lewat. Hubungi admin bila perlu dibuka kembali.');

    // Dosen tidak bisa membuka sendiri.
    $this->actingAs($dosen)->post(route('admin.kelas-kuliah.buka-kunci-nilai', $kelas), ['sampai' => now()->addDays(3)->toDateString()])->assertForbidden();

    $this->actingAs($admin)->post(route('admin.kelas-kuliah.buka-kunci-nilai', $kelas))->assertSessionHasErrors('sampai');
    $this->actingAs($admin)->post(route('admin.kelas-kuliah.buka-kunci-nilai', $kelas), ['sampai' => now()->addDays(3)->toDateString()])->assertSessionHas('success');

    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'A'])->assertSessionHas('success');
    expect($krs->fresh()->nilai)->toBe('A');

    // Kelas lain di tahun yang sama tetap terkunci.
    $lain = createMateriKelasKuliah($kelas->tahunAkademik);
    expect($lain->nilaiFinal())->toBeTrue();
});

it('reopening a finalized class before the deadline needs no new date', function () {
    [$kelas, $krs] = kelasBernilai();
    $kelas->finalisasiNilai($kelas->dosen->user);

    $this->actingAs(User::factory()->admin()->create())->post(route('admin.kelas-kuliah.buka-kunci-nilai', $kelas))->assertSessionHas('success');

    expect($kelas->fresh()->nilai_final_at)->toBeNull()->and($kelas->fresh()->nilaiFinal())->toBeFalse();
    $this->actingAs($kelas->dosen->user)->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'A'])->assertSessionHas('success');
});

it('keeps attendance editable after grades are finalized', function () {
    [$kelas] = kelasBernilai();
    $kelas->finalisasiNilai($kelas->dosen->user);

    $this->actingAs($kelas->dosen->user)->get(route('dosen.presensi.kelas', $kelas))
        ->assertInertia(fn ($page) => $page->where('terkunci', false));
});

it('stores the grade deadline on the tahun akademik', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tahun-akademik.store'), [
        'tahun' => '2030/2031', 'semester' => 'Ganjil', 'tanggal_mulai' => '2030-08-01', 'tanggal_akhir' => '2031-01-31',
        'tanggal_krs_awal' => '2030-08-01', 'tanggal_krs_akhir' => '2030-08-14', 'batas_input_nilai' => '2031-01-20', 'status' => false,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('tahun_akademik', ['tahun' => '2030/2031', 'batas_input_nilai' => '2031-01-20']);
});
