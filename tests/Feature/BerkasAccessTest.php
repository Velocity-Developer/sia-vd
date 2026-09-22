<?php

use App\Models\InfoKuliah;
use App\Models\Krs;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function kelasDenganMateri(): array
{
    Storage::fake('local');
    $kelas = createMateriKelasKuliah();
    Storage::disk('local')->put('materis/modul-abc123.pdf', 'isi modul');
    $materi = $kelas->materis()->create(['judul_materi' => 'Modul', 'pertemuan_ke' => 1, 'jenis' => 'Materi', 'file' => ['materis/modul-abc123.pdf'], 'uploaded_by' => $kelas->dosen->user_id]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);

    return [$kelas, $materi, $mahasiswa];
}

it('serves course files only to people in the class', function () {
    [$kelas, $materi, $mahasiswa] = kelasDenganMateri();
    $url = route('berkas.materi', [$materi, 0]);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($mahasiswa)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($kelas->dosen->user)->get($url)->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    $this->actingAs(User::factory()->mahasiswa()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->dosen()->create())->get($url)->assertForbidden();
    $this->actingAs($mahasiswa)->get(route('berkas.materi', [$materi, 5]))->assertNotFound();
});

it('keeps a submission visible only to its owner, the class dosen, and admin', function () {
    [$kelas, , $pemilik] = kelasDenganMateri();
    $teman = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $teman->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_tugas' => 'Tugas']);
    Storage::disk('local')->put('pengumpulan-tugas/jawaban-xyz.pdf', 'jawaban');
    $pengumpulan = PengumpulanTugas::create(['tugas_id' => $tugas->id, 'mahasiswa_id' => $pemilik->mahasiswaProfile->id, 'file_jawaban' => ['pengumpulan-tugas/jawaban-xyz.pdf'], 'submitted_at' => now()]);
    $url = route('berkas.pengumpulan', [$pengumpulan, 0]);

    $this->actingAs($pemilik)->get($url)->assertOk();
    $this->actingAs($kelas->dosen->user)->get($url)->assertOk();
    $this->actingAs($teman)->get($url)->assertForbidden();
});

it('serves info kuliah attachments to logged-in students and admins only', function () {
    Storage::fake('local');
    Storage::disk('local')->put('info-kuliahs/pengumuman.pdf', 'isi');
    $admin = User::factory()->admin()->create();
    $info = InfoKuliah::create(['information' => 'Pengumuman', 'file' => 'info-kuliahs/pengumuman.pdf', 'uploaded_by' => $admin->id]);

    $this->get(route('berkas.info-kuliah', $info))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->mahasiswa()->create())->get(route('berkas.info-kuliah', $info))->assertOk();
    $this->actingAs(User::factory()->dosen()->create())->get(route('berkas.info-kuliah', $info))->assertForbidden();
});

it('does not send parents\' data or addresses to the dosen class page', function () {
    [$kelas] = kelasDenganMateri();

    $this->actingAs($kelas->dosen->user)->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page
            ->where('kelasKuliah.krs.0.mahasiswa', fn ($mahasiswa) => collect($mahasiswa)->keys()->diff(['id', 'user_id', 'nim', 'prodi_id', 'user', 'prodi'])->isEmpty()));
});
