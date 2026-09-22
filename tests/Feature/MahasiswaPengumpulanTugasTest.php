<?php

use App\Models\Krs;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('denies task detail outside enrolled class', function () {
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_tugas' => 'Tugas']);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.tugas.show', $tugas))->assertForbidden();
});

it('stores multiple answer files and replaces previous submission', function () {
    Storage::fake('local');
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_tugas' => 'Tugas']);

    $response = $this->actingAs($mahasiswa)->post(route('mahasiswa.tugas.pengumpulan.store', $tugas), ['file_jawaban' => [UploadedFile::fake()->create('Jawaban Satu.pdf'), UploadedFile::fake()->create('Jawaban Dua.docx')]]);
    $response->assertRedirect();
    expect($tugas->pengumpulanTugas()->first()->file_jawaban)->toHaveCount(2);
    expect($tugas->pengumpulanTugas()->count())->toBe(1);
});

it('rejects answer submission after the deadline', function () {
    Storage::fake('local');
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_tugas' => 'Tugas Lewat', 'tenggat_waktu' => now()->subDay()]);

    $response = $this->actingAs($mahasiswa)->post(route('mahasiswa.tugas.pengumpulan.store', $tugas), ['file_jawaban' => [UploadedFile::fake()->create('Jawaban.pdf')]]);
    $response->assertRedirect(route('mahasiswa.tugas.show', $tugas));
    $response->assertSessionHas('error');
    expect($tugas->pengumpulanTugas()->count())->toBe(0);
});

it('accepts answer submission before the deadline', function () {
    Storage::fake('local');
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_tugas' => 'Tugas Aktif', 'tenggat_waktu' => now()->addDay()]);

    $this->actingAs($mahasiswa)->post(route('mahasiswa.tugas.pengumpulan.store', $tugas), ['file_jawaban' => [UploadedFile::fake()->create('Jawaban.pdf')]])->assertRedirect();
    expect($tugas->pengumpulanTugas()->count())->toBe(1);
});

it('rejects an answer file whose extension is not allowed even when its content looks valid', function () {
    Storage::fake('local');
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_tugas' => 'Tugas']);

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.tugas.pengumpulan.store', $tugas), ['file_jawaban' => [UploadedFile::fake()->create('jawaban.html', 10, 'image/png')]])
        ->assertSessionHasErrors('file_jawaban.0');

    expect($tugas->pengumpulanTugas()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
