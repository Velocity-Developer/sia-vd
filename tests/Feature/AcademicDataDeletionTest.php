<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Materi;
use App\Models\User;

it('does not delete a kelas kuliah that already has KRS', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'A']);

    $this->actingAs($admin)
        ->delete(route('admin.kelas-kuliah.destroy', $kelas))
        ->assertSessionHas('error');

    expect($kelas->fresh())->not->toBeNull()
        ->and(Krs::where('kelas_id', $kelas->id)->value('nilai'))->toBe('A');
});

it('still deletes a kelas kuliah without KRS', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();

    $this->actingAs($admin)
        ->delete(route('admin.kelas-kuliah.destroy', $kelas))
        ->assertSessionHas('success');

    expect(KelasKuliah::find($kelas->id))->toBeNull();
});

it('does not delete a mata kuliah that is used by a kelas', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();

    $this->actingAs($admin)
        ->delete(route('admin.mata-kuliah.destroy', $kelas->matkul_id))
        ->assertSessionHas('error');

    expect($kelas->fresh())->not->toBeNull();
});

it('does not delete a dosen who still teaches a kelas', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();
    $dosen = $kelas->dosen->user;

    $this->actingAs($admin)
        ->delete(route('admin.users.dosen.destroy', $dosen))
        ->assertSessionHas('error');

    expect($dosen->fresh())->not->toBeNull()
        ->and($kelas->fresh())->not->toBeNull();
});

it('does not delete a mahasiswa who has KRS', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);

    $this->actingAs($admin)
        ->delete(route('admin.users.mahasiswa.destroy', $mahasiswa))
        ->assertSessionHas('error');

    expect($mahasiswa->fresh())->not->toBeNull();
});

it('keeps uploaded content when the uploader account is deleted', function () {
    $admin = User::factory()->admin()->create();
    $uploader = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();
    $materi = $kelas->materis()->create(['judul_materi' => 'Pengantar', 'pertemuan_ke' => 1, 'jenis' => 'Materi', 'uploaded_by' => $uploader->id]);

    $this->actingAs($admin)
        ->delete(route('admin.users.karyawan.destroy', $uploader))
        ->assertSessionHas('success');

    expect(Materi::find($materi->id))->not->toBeNull()
        ->and(Materi::find($materi->id)->uploaded_by)->toBeNull();
});
