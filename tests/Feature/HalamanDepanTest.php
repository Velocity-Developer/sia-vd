<?php

use App\Models\InfoKuliah;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function buatPengumuman(User $admin, string $isi, ?string $kategori = null): InfoKuliah
{
    return InfoKuliah::create(['kategori' => $kategori, 'information' => $isi, 'file' => 'info-kuliahs/'.str()->random(8).'.pdf', 'uploaded_by' => $admin->id]);
}

it('no longer lists announcements on the login page', function () {
    buatPengumuman(User::factory()->admin()->create(), 'Pengumuman');

    $this->get(route('login'))->assertOk()->assertInertia(fn ($page) => $page->component('auth/Login')->missing('pengumuman'));
});

it('lists all announcements publicly on the pengumuman page', function () {
    $admin = User::factory()->admin()->create();
    foreach (range(1, 12) as $i) {
        buatPengumuman($admin, "Pengumuman {$i}")->forceFill(['created_at' => now()->subDays(20 - $i)])->save();
    }

    $this->get(route('pengumuman'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Portal/Pengumuman')
        ->has('pengumuman.data', 10)
        ->where('pengumuman.data.0.information', 'Pengumuman 12')
        ->where('pengumuman.total', 12)
        ->missing('pengumuman.data.0.file'));

    // Pengguna yang sudah masuk juga bisa membuka halaman ini.
    $this->actingAs(User::factory()->mahasiswa()->create())->get(route('pengumuman'))->assertOk();
});

it('builds the academic calendar from the active tahun akademik', function () {
    TahunAkademik::query()->update(['status' => false]);
    TahunAkademik::create([
        'tahun' => '2030/2031', 'semester' => 'Ganjil', 'status' => true,
        'tanggal_mulai' => '2030-09-01', 'tanggal_akhir' => '2031-01-31',
        'tanggal_krs_awal' => '2030-08-20', 'tanggal_krs_akhir' => '2030-09-05',
        'batas_input_nilai' => '2031-02-10',
    ]);

    $this->get(route('kalender-akademik'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Portal/KalenderAkademik')
        ->where('tahunAkademik', '2030/2031 Ganjil')
        ->where('kegiatan.0', ['kegiatan' => 'Pengisian KRS', 'mulai' => '2030-08-20', 'selesai' => '2030-09-05'])
        ->where('kegiatan.1', ['kegiatan' => 'Perkuliahan', 'mulai' => '2030-09-01', 'selesai' => '2031-01-31'])
        ->where('kegiatan.2', ['kegiatan' => 'Batas input nilai', 'mulai' => '2031-02-10', 'selesai' => null])
        ->has('kegiatan', 3));
});

it('saves an optional kategori for informasi & pengumuman', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.info-kuliah.store'), [
        'kategori' => 'Beasiswa',
        'information' => 'Pendaftaran beasiswa dibuka.',
        'file' => UploadedFile::fake()->create('beasiswa.pdf', 50, 'application/pdf'),
    ])->assertRedirect();
    expect(InfoKuliah::first()->kategori)->toBe('Beasiswa');

    $this->actingAs($admin)->put(route('admin.info-kuliah.update', InfoKuliah::first()), ['kategori' => '', 'information' => 'Diperbarui.'])->assertRedirect();
    expect(InfoKuliah::first()->only(['kategori', 'information']))->toBe(['kategori' => null, 'information' => 'Diperbarui.']);
});
