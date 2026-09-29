<?php

use App\Models\Krs;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;

it('renders the lecturer dashboard with classes, ungraded work, and the grade deadline', function () {
    $kelas = createMateriKelasKuliah();
    $kelas->tahunAkademik->update(['batas_input_nilai' => today()->addDays(2)->toDateString()]);
    $dosen = $kelas->dosen->user;
    [$a, $b] = User::factory()->mahasiswa()->count(2)->create();
    foreach ([$a, $b] as $m) {
        Krs::create(['mahasiswa_id' => $m->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    }
    $a->mahasiswaProfile->update(['dosen_wali_id' => $kelas->dosen_id]);
    $b->mahasiswaProfile->update(['dosen_wali_id' => User::factory()->dosen()->create()->dosenProfile->id]);

    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $dosen->id, 'judul_tugas' => 'Tugas 1']);
    PengumpulanTugas::create(['tugas_id' => $tugas->id, 'mahasiswa_id' => $a->mahasiswaProfile->id, 'file_jawaban' => ['pengumpulan-tugas/a.pdf'], 'submitted_at' => now()]);
    PengumpulanTugas::create(['tugas_id' => $tugas->id, 'mahasiswa_id' => $b->mahasiswaProfile->id, 'file_jawaban' => ['pengumpulan-tugas/b.pdf'], 'submitted_at' => now(), 'nilai' => 80]);

    $this->actingAs($dosen)->get(route('dosen.dashboard'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dosen/Dashboard')
        ->where('ringkasan.kelas_diampu', 1)
        ->where('ringkasan.mahasiswa_diajar', 2)
        ->where('ringkasan.mahasiswa_wali', 1)
        ->where('kelas.0.peserta', 2)
        ->where('kelas.0.nilai_final', false)
        ->where('perluDinilai', [[
            'jenis' => 'Tugas', 'judul' => 'Tugas 1', 'kelas' => 'Algoritma dan Pemrograman · '.$kelas->kode_kelas, 'jumlah' => 1,
            'tautan' => route('dosen.kelas-kuliah.tugas.show', [$kelas->id, $tugas->id]),
        ]])
        ->where('pengingatNilai.pesan.0.penting', true)
        ->where('pengingatNilai.pesan.0.teks', fn (string $t) => str_contains($t, '(2 hari lagi)')));

    // Sesudah difinalisasi, pengingat batas nilai hilang.
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas));
    $this->actingAs($dosen)->get(route('dosen.dashboard'))->assertInertia(fn ($page) => $page
        ->where('pengingatNilai', null)
        ->where('kelas.0.nilai_final', true));
});
