<?php

use App\Models\BobotNilai;
use App\Models\Krs;
use App\Models\SkalaNilai;
use App\Models\User;
use App\Transkrip;

it('prefills an unset prodi from the general grading scale', function () {
    $prodi = createMateriKelasKuliah()->mataKuliah->prodi;

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.bobot-nilai.index', ['prodi' => $prodi->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/BobotNilai')
            ->where('prodiId', $prodi->id)
            ->where('belumDiatur', true)
            ->has('bobotNilai', SkalaNilai::count()));
});

it('saves grade weights per prodi without touching other prodi or the general scale', function () {
    $prodi = createMateriKelasKuliah()->mataKuliah->prodi;
    $lain = createMateriKelasKuliah()->mataKuliah->prodi;
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.bobot-nilai.update', $prodi), [
        'bobot_nilai' => [
            ['huruf' => 'a', 'bobot' => 4, 'angka_minimal' => 85, 'lulus' => true, 'boleh_diulang' => false],
            ['huruf' => 'B+', 'bobot' => 3.5, 'angka_minimal' => 75, 'lulus' => true, 'boleh_diulang' => false],
            ['huruf' => 'E', 'bobot' => 0, 'angka_minimal' => null, 'lulus' => false, 'boleh_diulang' => true],
        ],
    ])->assertSessionHas('success');

    expect(BobotNilai::where('prodi_id', $prodi->id)->orderByDesc('bobot')->pluck('huruf')->all())->toBe(['A', 'B+', 'E'])
        ->and(BobotNilai::where('prodi_id', $prodi->id)->where('huruf', 'B+')->value('bobot'))->toBe(3.5)
        ->and(BobotNilai::where('prodi_id', $lain->id)->count())->toBe(0)
        ->and(SkalaNilai::count())->toBe(5);

    $this->actingAs($admin)->get(route('admin.bobot-nilai.index', ['prodi' => $prodi->id]))
        ->assertInertia(fn ($page) => $page->where('belumDiatur', false)->has('bobotNilai', 3));

    $this->actingAs($admin)->delete(route('admin.bobot-nilai.destroy', $prodi))->assertSessionHas('success');
    expect(BobotNilai::where('prodi_id', $prodi->id)->count())->toBe(0);
});

it('applies the same rules as the general grading scale', function (array $baris, string $error) {
    $prodi = createMateriKelasKuliah()->mataKuliah->prodi;

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.bobot-nilai.update', $prodi), ['bobot_nilai' => $baris])
        ->assertSessionHasErrors($error);

    expect(BobotNilai::count())->toBe(0);
})->with([
    'tanpa huruf tidak lulus' => [[['huruf' => 'A', 'bobot' => 4, 'lulus' => true, 'boleh_diulang' => false]], 'bobot_nilai'],
    'huruf ganda' => [[
        ['huruf' => 'A', 'bobot' => 4, 'lulus' => true, 'boleh_diulang' => false],
        ['huruf' => 'a', 'bobot' => 0, 'lulus' => false, 'boleh_diulang' => true],
    ], 'bobot_nilai.0.huruf'],
    'angka minimal terbalik' => [[
        ['huruf' => 'A', 'bobot' => 4, 'angka_minimal' => 50, 'lulus' => true, 'boleh_diulang' => false],
        ['huruf' => 'E', 'bobot' => 0, 'angka_minimal' => 60, 'lulus' => false, 'boleh_diulang' => true],
    ], 'bobot_nilai'],
]);

it('keeps grade weights admin-only', function () {
    $prodi = createMateriKelasKuliah()->mataKuliah->prodi;

    $this->actingAs(User::factory()->dosen()->create())->get(route('admin.bobot-nilai.index'))->assertForbidden();
    $this->actingAs(User::factory()->mahasiswa()->create())->put(route('admin.bobot-nilai.update', $prodi), [])->assertForbidden();
});

/**
 * Bobot prodi: A = 3.5, B+ = 3.25, E tidak lulus.
 */
function aturBobotProdi(int $prodiId): void
{
    foreach ([['A', 3.5, 85, true, false], ['B+', 3.25, 70, true, false], ['E', 0, 0, false, true]] as [$huruf, $bobot, $angka, $lulus, $ulang]) {
        BobotNilai::create(['prodi_id' => $prodiId, 'huruf' => $huruf, 'bobot' => $bobot, 'angka_minimal' => $angka, 'lulus' => $lulus, 'boleh_diulang' => $ulang]);
    }
}

it('weighs grades with the course prodi weights, falling back to the general scale', function () {
    $kelasProdi = createMateriKelasKuliah();
    $kelasUmum = createMateriKelasKuliah();
    aturBobotProdi($kelasProdi->mataKuliah->prodi_id);
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasProdi->id, 'nilai' => 'A', 'nilai_divalidasi_at' => now()]);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasUmum->id, 'nilai' => 'A', 'nilai_divalidasi_at' => now()]);

    // 3 SKS × 3.5 (prodi) + 3 SKS × 4 (umum) = 22.5 / 6 SKS.
    expect(Transkrip::ringkasan($mahasiswa->mahasiswaProfile->id)['ipk'])->toBe(3.75);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.transkrip'))
        ->assertInertia(fn ($page) => $page->where('ringkasan.ipk', 3.75)->where('ringkasan.totalMutu', 22.5));
});

it('converts the lecturer score with the prodi letters', function () {
    $kelas = createMateriKelasKuliah();
    aturBobotProdi($kelas->mataKuliah->prodi_id);
    $krs = Krs::create(['mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('skalaNilai', ['A', 'B+', 'E']));

    // Skala umum memberi B untuk 75; skala prodi memberi B+.
    isiNilaiKomponen($this, $kelas, $krs, 75)->assertSessionHas('success');
    expect($krs->fresh()->nilai)->toBe('B+');

    expect($krs->fresh()->bobotNilai())->toBe(3.25);
});

it('does not drop prodi letters already used, nor fall back to a scale without them', function () {
    $kelas = createMateriKelasKuliah();
    $prodi = $kelas->mataKuliah->prodi;
    aturBobotProdi($prodi->id);
    Krs::create(['mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'B+']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.bobot-nilai.index', ['prodi' => $prodi->id]))
        ->assertInertia(fn ($page) => $page->where('bobotNilai.1.huruf', 'B+')->where('bobotNilai.1.dipakai', 1));

    $this->actingAs($admin)->put(route('admin.bobot-nilai.update', $prodi), ['bobot_nilai' => [
        ['huruf' => 'A', 'bobot' => 4, 'lulus' => true, 'boleh_diulang' => false],
        ['huruf' => 'E', 'bobot' => 0, 'lulus' => false, 'boleh_diulang' => true],
    ]])->assertSessionHasErrors(['bobot_nilai' => 'Nilai B+ masih dipakai di KRS mata kuliah prodi ini sehingga tidak bisa dihapus.']);

    // Skala umum tidak punya B+, jadi bobot prodi tidak boleh dihapus.
    $this->actingAs($admin)->delete(route('admin.bobot-nilai.destroy', $prodi))->assertSessionHas('error');
    expect(BobotNilai::where('prodi_id', $prodi->id)->count())->toBe(3);
});

it('lets the general scale drop a letter used only by prodi with their own weights', function () {
    $kelas = createMateriKelasKuliah();
    aturBobotProdi($kelas->mataKuliah->prodi_id);
    Krs::create(['mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'E']);
    $tanpaE = SkalaNilai::query()->where('huruf', '!=', 'E')->get(['huruf', 'bobot', 'lulus', 'boleh_diulang'])->toArray();
    $tanpaE[] = ['huruf' => 'F', 'bobot' => 0, 'lulus' => false, 'boleh_diulang' => true];

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pengaturan-akademik.skala-nilai'), ['skala_nilai' => $tanpaE])
        ->assertSessionHas('success');

    expect(SkalaNilai::where('huruf', 'E')->exists())->toBeFalse();
});
