<?php

use App\Models\InfoKuliah;
use App\Models\Materi;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('rejects executable or browser-rendered files on materi uploads', function (string $filename) {
    Storage::fake('local');
    $kelas = createMateriKelasKuliah();
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.materi.store', $kelas), [
        'judul_materi' => 'Pengantar',
        'pertemuan_ke' => 1,
        'jenis' => 'Materi',
        'file' => [UploadedFile::fake()->create($filename, 10)],
    ])->assertSessionHasErrors('file.0');

    expect(Materi::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['shell.php', 'shell.phtml', 'page.html', 'image.svg', 'script.js', 'noextension']);

it('accepts document uploads on materi', function () {
    Storage::fake('local');
    $kelas = createMateriKelasKuliah();

    $this->actingAs($kelas->dosen->user)->post(route('dosen.kelas-kuliah.materi.store', $kelas), [
        'judul_materi' => 'Pengantar',
        'pertemuan_ke' => 1,
        'jenis' => 'Materi',
        'file' => [UploadedFile::fake()->create('Modul.PDF', 10)],
    ])->assertSessionHasNoErrors();

    expect(Materi::firstOrFail()->file[0])->toEndWith('.pdf');
});

it('rejects a php file on info kuliah and names accepted files by their allowed extension', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.info-kuliah.store'), [
        'information' => 'Pengumuman',
        'file' => UploadedFile::fake()->createWithContent('info.php', '<?php echo 1;'),
    ])->assertSessionHasErrors('file');

    $this->actingAs($admin)->post(route('admin.info-kuliah.store'), [
        'information' => 'Pengumuman',
        'file' => UploadedFile::fake()->create('jadwal.pdf', 10),
    ])->assertSessionHasNoErrors();

    expect(InfoKuliah::firstOrFail()->file)->toStartWith('info-kuliahs/')->toEndWith('.pdf');
});
