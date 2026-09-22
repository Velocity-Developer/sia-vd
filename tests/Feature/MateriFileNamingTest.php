<?php

use App\Models\Materi;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores materi files with sanitized original name plus random suffix', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();

    $this->actingAs($admin)->post(route('admin.kelas-kuliah.materi.store', $kelas), [
        'judul_materi' => 'Pengantar',
        'pertemuan_ke' => 1,
        'jenis' => 'Materi',
        'file' => [UploadedFile::fake()->create('Modul Pertemuan 1.pdf', 100)],
    ])->assertRedirect()->assertSessionHas('materi_success', 'Materi berhasil ditambahkan.');

    $materi = Materi::firstOrFail();
    expect($materi->jenis)->toBe('Materi');

    $files = $materi->file;
    expect($files)->toBeArray()->toHaveCount(1);

    $path = $files[0];
    $basename = basename($path);
    expect($path)->toStartWith('materis/');
    Storage::disk('public')->assertExists($path);
    expect($basename)->toContain('modul_pertemuan_1')
        ->toEndWith('.pdf')
        ->not->toBe('Modul Pertemuan 1.pdf')
        ->toMatch('/^modul_pertemuan_1-[a-z0-9]{6}\.pdf$/');
});

it('stores distinct paths when uploading the same original name twice', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();

    $this->actingAs($admin)->post(route('admin.kelas-kuliah.materi.store', $kelas), [
        'judul_materi' => 'Pengantar',
        'pertemuan_ke' => 1,
        'jenis' => 'Pengumuman',
        'file' => [UploadedFile::fake()->create('Tugas Akhir.pdf', 100), UploadedFile::fake()->create('Tugas Akhir.pdf', 100)],
    ])->assertRedirect()->assertSessionHas('materi_success', 'Materi berhasil ditambahkan.');

    $materi = Materi::firstOrFail();
    expect($materi->jenis)->toBe('Pengumuman');
    $files = $materi->file;
    expect($files)->toBeArray()->toHaveCount(2);
    expect($files[0])->not->toBe($files[1]);

    foreach ($files as $path) {
        Storage::disk('public')->assertExists($path);
        expect(basename($path))->toMatch('/^tugas_akhir-[a-z0-9]{6}\.pdf$/');
    }
});
