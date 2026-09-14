<?php

use App\Models\Krs;
use App\Models\Materi;
use App\Models\User;
use App\Role;

it('shows taken materi detail with its files', function () {
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->create(['role' => Role::Mahasiswa]);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $materi = Materi::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_materi' => 'Materi Detail', 'pertemuan_ke' => 2, 'jenis' => 'Materi', 'file' => ['materis/file.pdf'], 'catatan' => 'Catatan detail']);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.materi.show', $materi))->assertOk()->assertInertia(fn ($page) => $page->component('Mahasiswa/MateriShow')->where('materi.id', $materi->id)->where('materi.file.0', 'materis/file.pdf'));
});

it('denies materi detail from classes not taken', function () {
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->create(['role' => Role::Mahasiswa]);
    $materi = Materi::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_materi' => 'Materi Terlarang', 'pertemuan_ke' => 1, 'jenis' => 'Materi']);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.materi.show', $materi))->assertForbidden();
});
