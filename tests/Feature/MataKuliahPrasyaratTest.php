<?php

use App\Models\MataKuliah;
use App\Models\MataKuliahPrasyarat;
use App\Models\User;

/**
 * Mata kuliah semester $semester di prodi yang sama dengan $acuan.
 */
function matkulSeprodi(MataKuliah $acuan, int $semester): MataKuliah
{
    $kode = 'PR'.bin2hex(random_bytes(3));

    return MataKuliah::create(['kode_matkul' => $kode, 'nama_matkul' => "Mata Kuliah {$kode}", 'sks' => 2, 'semester' => $semester, 'jenis' => 'Wajib', 'prodi_id' => $acuan->prodi_id]);
}

it('lets admin add, list, edit, and delete a prerequisite pair', function () {
    $dasar = createMateriKelasKuliah()->mataKuliah; // semester 1
    $lanjut = matkulSeprodi($dasar, 3);
    $lain = matkulSeprodi($dasar, 2);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.prasyarat.store'), ['mata_kuliah_id' => $lanjut->id, 'prasyarat_id' => $dasar->id])
        ->assertRedirect(route('admin.prasyarat.index'));
    expect($lanjut->prasyarat()->pluck('mata_kuliahs.id')->all())->toBe([$dasar->id]);
    $pasangan = MataKuliahPrasyarat::firstOrFail();

    $this->actingAs($admin)->get(route('admin.prasyarat.index', ['search' => $dasar->kode_matkul]))
        ->assertInertia(fn ($page) => $page->component('Admin/Prasyarat')->has('prasyarat.data', 1)
            ->where('prasyarat.data.0.mata_kuliah.id', $lanjut->id)->where('prasyarat.data.0.prasyarat.id', $dasar->id));

    $this->actingAs($admin)->get(route('admin.prasyarat.edit', $pasangan))->assertOk();
    $this->actingAs($admin)->put(route('admin.prasyarat.update', $pasangan), ['mata_kuliah_id' => $lanjut->id, 'prasyarat_id' => $lain->id])
        ->assertSessionHas('success');
    expect($pasangan->fresh()->prasyarat_id)->toBe($lain->id);

    $this->actingAs($admin)->delete(route('admin.prasyarat.destroy', $pasangan))->assertSessionHas('success');
    expect(MataKuliahPrasyarat::count())->toBe(0);
});

it('rejects invalid prerequisite pairs', function (string $kasus, string $pesan) {
    $dasar = createMateriKelasKuliah()->mataKuliah; // semester 1
    $lanjut = matkulSeprodi($dasar, 3);
    $prodiLain = createMateriKelasKuliah()->mataKuliah;
    MataKuliahPrasyarat::create(['mata_kuliah_id' => $lanjut->id, 'prasyarat_id' => $dasar->id]);

    [$mk, $pra] = match ($kasus) {
        'diri sendiri' => [$lanjut, $lanjut],
        'ganda' => [$lanjut, $dasar],
        'prodi lain' => [$lanjut, $prodiLain],
        'semester terbalik' => [$dasar, $lanjut],
    };

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.prasyarat.store'), ['mata_kuliah_id' => $mk->id, 'prasyarat_id' => $pra->id])
        ->assertSessionHasErrors(['prasyarat_id' => str_replace(':pra', $pra->nama_matkul, $pesan)]);

    expect(MataKuliahPrasyarat::count())->toBe(1);
})->with([
    ['diri sendiri', 'Mata kuliah tidak bisa menjadi prasyarat dirinya sendiri.'],
    ['ganda', 'Prasyarat ini sudah terdaftar untuk mata kuliah tersebut.'],
    ['prodi lain', 'Prasyarat harus mata kuliah dari program studi yang sama.'],
    ['semester terbalik', 'Prasyarat harus dari semester sebelum semester 1: :pra (smt 3).'],
]);

it('keeps the prerequisite menu admin-only', function () {
    $this->actingAs(User::factory()->dosen()->create())->get(route('admin.prasyarat.index'))->assertForbidden();
    $this->actingAs(User::factory()->mahasiswa()->create())->get(route('admin.prasyarat.create'))->assertForbidden();
});
