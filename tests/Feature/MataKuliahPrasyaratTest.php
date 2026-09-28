<?php

use App\Models\MataKuliah;
use App\Models\User;

function matkulPrasyarat(int $semester, ?int $prodiId = null): MataKuliah
{
    $prodiId ??= createMateriKelasKuliah()->mataKuliah->prodi_id;
    $suffix = bin2hex(random_bytes(3));

    return MataKuliah::create(['kode_matkul' => "MK{$suffix}", 'nama_matkul' => "Matkul {$suffix}", 'sks' => 3, 'semester' => $semester, 'jenis' => 'Wajib', 'prodi_id' => $prodiId]);
}

function payloadMatkul(MataKuliah $contoh, array $ubah = []): array
{
    return array_replace(['kode_matkul' => 'MK-BARU', 'nama_matkul' => 'Basis Data Lanjut', 'sks' => 3, 'semester' => 4, 'jenis' => 'Wajib', 'prodi_id' => $contoh->prodi_id, 'prasyarat_ids' => []], $ubah);
}

it('saves prerequisites from an earlier semester of the same study program', function () {
    $admin = User::factory()->admin()->create();
    $dasar = matkulPrasyarat(2);

    $this->actingAs($admin)->post(route('admin.mata-kuliah.store'), payloadMatkul($dasar, ['prasyarat_ids' => [$dasar->id]]))
        ->assertSessionHasNoErrors();

    $baru = MataKuliah::where('kode_matkul', 'MK-BARU')->firstOrFail();
    expect($baru->prasyarat->pluck('id')->all())->toBe([$dasar->id]);

    $this->actingAs($admin)->get(route('admin.mata-kuliah.show', $dasar))
        ->assertInertia(fn ($page) => $page->where('mataKuliah.menjadi_prasyarat.0.id', $baru->id));

    $this->actingAs($admin)->put(route('admin.mata-kuliah.update', $baru), payloadMatkul($dasar))->assertSessionHasNoErrors();
    expect($baru->prasyarat()->count())->toBe(0);
});

it('rejects prerequisites that are not from an earlier semester or another study program', function () {
    $admin = User::factory()->admin()->create();
    $sama = matkulPrasyarat(4);
    $prodiLain = matkulPrasyarat(1);

    $this->actingAs($admin)->post(route('admin.mata-kuliah.store'), payloadMatkul($sama, ['prasyarat_ids' => [$sama->id]]))
        ->assertSessionHasErrors('prasyarat_ids');
    $this->actingAs($admin)->post(route('admin.mata-kuliah.store'), payloadMatkul($sama, ['prasyarat_ids' => [$prodiLain->id]]))
        ->assertSessionHasErrors('prasyarat_ids.0');

    expect(MataKuliah::where('kode_matkul', 'MK-BARU')->exists())->toBeFalse();
});

it('keeps prerequisites acyclic when the semester of a required course changes', function () {
    $admin = User::factory()->admin()->create();
    $dasar = matkulPrasyarat(2);
    $lanjutan = matkulPrasyarat(4, $dasar->prodi_id);
    $lanjutan->prasyarat()->attach($dasar->id);

    $this->actingAs($admin)->put(route('admin.mata-kuliah.update', $dasar), payloadMatkul($dasar, ['kode_matkul' => $dasar->kode_matkul, 'semester' => 4]))
        ->assertSessionHasErrors('semester');

    expect($dasar->fresh()->semester)->toBe(2);
});
