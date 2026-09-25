<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RemidiPeserta;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\User;
use App\UsulanRemidi;

/**
 * Kelas final dengan mahasiswa bernilai A, C, D, E, E dan satu tanpa nilai.
 *
 * @return array{0: KelasKuliah, 1: array<string, User>}
 */
function kelasRemidi(): array
{
    $kelas = createMateriKelasKuliah();
    $mhs = [];
    foreach (['a' => 'A', 'c' => 'C', 'd' => 'D', 'e1' => 'E', 'e2' => 'E', 'kosong' => null] as $kunci => $nilai) {
        $mhs[$kunci] = User::factory()->mahasiswa()->create();
        Krs::create(['mahasiswa_id' => $mhs[$kunci]->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => $nilai]);
    }
    $kelas->finalisasiNilai($kelas->dosen->user);

    return [$kelas->fresh(), $mhs];
}

function idMhs(User ...$users): array
{
    return array_map(fn (User $u): int => $u->mahasiswaProfile->id, $users);
}

function usulan(KelasKuliah $kelas): array
{
    return collect(UsulanRemidi::susun($kelas->fresh())['mahasiswa'])->where('diusulkan', true)->pluck('mahasiswa_id')->sort()->values()->all();
}

it('proposes D and E students when the class has no UAS in the system', function () {
    [$kelas, $m] = kelasRemidi();

    expect(usulan($kelas))->toBe(collect(idMhs($m['d'], $m['e1'], $m['e2']))->sort()->values()->all());
    expect(UsulanRemidi::susun($kelas)['ada_uas'])->toBeFalse();
});

it('skips students who did not sit the UAS, per exam mode', function (string $mode) {
    [$kelas, $m] = kelasRemidi();
    $uas = Ujian::create(['kelas_id' => $kelas->id, 'jenis' => 'uas', 'mode' => $mode, 'tanggal' => now()->subDays(2)->toDateString(), 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'status' => 'terbit']);
    $ikut = [$m['d'], $m['e1']];

    foreach ($ikut as $user) {
        match ($mode) {
            'online_soal' => QuizAttempt::create(['quiz_id' => Quiz::firstOrCreate(['ujian_id' => $uas->id], ['kelas_id' => $kelas->id, 'nama_quiz' => 'UAS', 'uploaded_by' => $kelas->dosen->user_id])->id, 'mahasiswa_id' => $user->mahasiswaProfile->id, 'started_at' => now()->subDays(2)]),
            'online_berkas' => UjianJawaban::create(['ujian_id' => $uas->id, 'mahasiswa_id' => $user->mahasiswaProfile->id, 'berkas' => ['a.pdf'], 'dikumpulkan_at' => now()->subDays(2)]),
            default => UjianJawaban::create(['ujian_id' => $uas->id, 'mahasiswa_id' => $user->mahasiswaProfile->id, 'nilai' => 30]),
        };
    }
    // Baris tatap muka tanpa nilai = belum dinilai, dianggap tidak ikut.
    if ($mode === 'tatap_muka') {
        UjianJawaban::create(['ujian_id' => $uas->id, 'mahasiswa_id' => $m['e2']->mahasiswaProfile->id, 'nilai' => null]);
    }

    expect(usulan($kelas))->toBe(collect(idMhs($m['d'], $m['e1']))->sort()->values()->all());
})->with(['tatap_muka', 'online_berkas', 'online_soal']);

it('lets the dosen adjust and lock the list, storing the grade at lock time', function () {
    [$kelas, $m] = kelasRemidi();
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->has('remidi.mahasiswa', 6)->where('remidi.dikunci_at', null));

    // Coret e2, tambah c.
    $pilih = idMhs($m['d'], $m['e1'], $m['c']);
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.remidi.kunci', $kelas), ['mahasiswa_ids' => $pilih])->assertSessionHas('success');

    expect($kelas->fresh()->remidi_dikunci_at)->not->toBeNull();
    $rows = RemidiPeserta::where('kelas_id', $kelas->id)->get()->keyBy('mahasiswa_id');
    expect($rows->keys()->sort()->values()->all())->toBe(collect($pilih)->sort()->values()->all())
        ->and($rows[$m['c']->mahasiswaProfile->id]->diusulkan)->toBeFalse()
        ->and($rows[$m['c']->mahasiswaProfile->id]->nilai_awal)->toBe('C')
        ->and($rows[$m['e1']->mahasiswaProfile->id]->diusulkan)->toBeTrue();

    // Setelah dikunci dosen tidak bisa mengubah.
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.remidi.kunci', $kelas), ['mahasiswa_ids' => []])->assertSessionHas('error');
    expect(RemidiPeserta::where('kelas_id', $kelas->id)->count())->toBe(3);
});

it('lets admin reopen the list and keeps the saved choice as the new default', function () {
    [$kelas, $m] = kelasRemidi();
    $this->actingAs($kelas->dosen->user)->post(route('dosen.kelas-kuliah.remidi.kunci', $kelas), ['mahasiswa_ids' => idMhs($m['e1'])]);

    $this->actingAs($kelas->dosen->user)->post(route('admin.kelas-kuliah.remidi.buka', $kelas))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.kelas-kuliah.remidi.buka', $kelas))->assertSessionHas('success');

    $terpilih = collect(UsulanRemidi::susun($kelas->fresh())['mahasiswa'])->where('terpilih', true)->pluck('mahasiswa_id')->all();
    expect($terpilih)->toBe(idMhs($m['e1']));

    $this->actingAs($kelas->dosen->user)->post(route('dosen.kelas-kuliah.remidi.kunci', $kelas), ['mahasiswa_ids' => idMhs($m['e1'], $m['e2'])])->assertSessionHas('success');
    expect(RemidiPeserta::where('kelas_id', $kelas->id)->count())->toBe(2);
});

it('refuses the list before grades are final, and students outside the class', function () {
    $kelas = createMateriKelasKuliah();
    $mhs = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'E']);
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.show', $kelas))->assertInertia(fn ($page) => $page->where('remidi', null));
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.remidi.kunci', $kelas), ['mahasiswa_ids' => idMhs($mhs)])->assertSessionHas('error');

    $kelas->finalisasiNilai($dosen);
    $luar = User::factory()->mahasiswa()->create();
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.remidi.kunci', $kelas), ['mahasiswa_ids' => idMhs($luar)])->assertStatus(422);

    $lain = createMateriKelasKuliah($kelas->tahunAkademik);
    $lain->finalisasiNilai($lain->dosen->user);
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.remidi.kunci', $lain), ['mahasiswa_ids' => []])->assertForbidden();
    expect(RemidiPeserta::count())->toBe(0);
});
