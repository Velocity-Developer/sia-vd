<?php

use App\Models\Materi;
use App\Models\User;

it('keeps dosen out of other dosens\' classes in the shared content controllers', function () {
    $kelas = createMateriKelasKuliah();
    $materi = $kelas->materis()->create(['judul_materi' => 'Modul', 'pertemuan_ke' => 1, 'jenis' => 'Materi', 'uploaded_by' => $kelas->dosen->user_id]);
    $quiz = $kelas->quizzes()->create(['nama_quiz' => 'Quiz', 'uploaded_by' => $kelas->dosen->user_id]);
    $dosenLain = User::factory()->dosen()->create();

    $this->actingAs($dosenLain)->get(route('dosen.kelas-kuliah.materi.create', $kelas))->assertForbidden();
    $this->actingAs($dosenLain)->post(route('dosen.kelas-kuliah.materi.store', $kelas), ['judul_materi' => 'Susupan', 'pertemuan_ke' => 1, 'jenis' => 'Materi'])->assertForbidden();
    $this->actingAs($dosenLain)->delete(route('dosen.kelas-kuliah.materi.destroy', [$kelas, $materi]))->assertForbidden();
    $this->actingAs($dosenLain)->get(route('dosen.kelas-kuliah.quiz.show', [$kelas, $quiz]))->assertForbidden();
    $this->actingAs($dosenLain)->get(route('dosen.kelas-kuliah.tugas.create', $kelas))->assertForbidden();

    expect(Materi::count())->toBe(1);
});

it('does not let a dosen duplicate content into another dosen\'s class', function () {
    $kelas = createMateriKelasKuliah();
    $kelasOrangLain = createMateriKelasKuliah();
    $materi = $kelas->materis()->create(['judul_materi' => 'Modul', 'pertemuan_ke' => 1, 'jenis' => 'Materi', 'uploaded_by' => $kelas->dosen->user_id]);

    $this->actingAs($kelas->dosen->user)
        ->post(route('dosen.kelas-kuliah.materi.duplicate', [$kelas, $materi]), ['target_ids' => [$kelasOrangLain->id]])
        ->assertNotFound();

    expect($kelasOrangLain->materis()->count())->toBe(0);
});

it('renders the shared pages with the role prefix', function () {
    $kelas = createMateriKelasKuliah();

    $this->actingAs($kelas->dosen->user)->get(route('dosen.kelas-kuliah.materi.create', $kelas))
        ->assertInertia(fn ($page) => $page->component('Kelas/MateriForm')->where('peran', 'dosen'));
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->component('Kelas/KelasKuliahShow')->where('peran', 'admin'));
});
