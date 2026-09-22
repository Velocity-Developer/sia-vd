<?php

use App\Models\User;

it('duplicates materi to selected classes', function () {
    $admin = User::factory()->admin()->create();
    $sourceClass = createMateriKelasKuliah();
    $targetClass = createMateriKelasKuliah();
    $materi = $sourceClass->materis()->create([
        'judul_materi' => 'Relasi Basis Data',
        'pertemuan_ke' => 5,
        'jenis' => 'Materi',
        'file' => ['materis/relasi.pdf'],
        'catatan' => 'Pelajari normalisasi.',
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.kelas-kuliah.materi.duplicate', [$sourceClass, $materi]), ['target_ids' => [$targetClass->id]])
        ->assertRedirect(route('admin.kelas-kuliah.show', $sourceClass))
        ->assertSessionHas('materi_success', "Materi berhasil diduplikasi ke: {$targetClass->kode_kelas}.");

    $this->assertDatabaseHas('materis', [
        'kelas_id' => $targetClass->id,
        'judul_materi' => 'Relasi Basis Data',
        'pertemuan_ke' => 5,
        'uploaded_by' => $admin->id,
    ]);
});

it('duplicates tugas to selected classes', function () {
    $admin = User::factory()->admin()->create();
    $sourceClass = createMateriKelasKuliah();
    $targetClass = createMateriKelasKuliah();
    $tugas = $sourceClass->tugas()->create([
        'judul_tugas' => 'ERD Sistem Akademik',
        'file' => ['tugas/erd.pdf'],
        'catatan' => 'Gunakan notasi crow foot.',
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.kelas-kuliah.tugas.duplicate', [$sourceClass, $tugas]), ['target_ids' => [$targetClass->id]])
        ->assertRedirect(route('admin.kelas-kuliah.show', $sourceClass))
        ->assertSessionHas('tugas_success', "Tugas berhasil diduplikasi ke: {$targetClass->kode_kelas}.");

    $this->assertDatabaseHas('tugas', [
        'kelas_id' => $targetClass->id,
        'judul_tugas' => 'ERD Sistem Akademik',
        'uploaded_by' => $admin->id,
    ]);
});

it('duplicates quiz and its questions to selected classes', function () {
    $admin = User::factory()->admin()->create();
    $sourceClass = createMateriKelasKuliah();
    $targetClass = createMateriKelasKuliah();
    $quiz = $sourceClass->quizzes()->create([
        'nama_quiz' => 'Normalisasi Data',
        'waktu_pengerjaan' => 30,
        'catatan' => 'Jawab seluruh soal.',
        'uploaded_by' => $admin->id,
    ]);
    $quiz->questions()->create([
        'question_text' => 'Apa kepanjangan dari ERD?',
        'question_type' => 'single_choice',
        'question_option' => [
            ['text' => 'Entity Relationship Diagram', 'is_correct' => true],
            ['text' => 'Electronic Record Data', 'is_correct' => false],
        ],
        'points' => 10,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.kelas-kuliah.quiz.duplicate', [$sourceClass, $quiz]), ['target_ids' => [$targetClass->id]])
        ->assertRedirect(route('admin.kelas-kuliah.show', $sourceClass))
        ->assertSessionHas('quiz_success', "Quiz berhasil diduplikasi ke: {$targetClass->kode_kelas}.");

    $copiedQuiz = $targetClass->quizzes()->where('nama_quiz', 'Normalisasi Data')->firstOrFail();

    expect($copiedQuiz->uploaded_by)->toBe($admin->id)
        ->and($copiedQuiz->questions)->toHaveCount(1)
        ->and($copiedQuiz->questions->first()->question_text)->toBe('Apa kepanjangan dari ERD?');
});

it('rejects the source class as a duplication target', function () {
    $admin = User::factory()->admin()->create();
    $sourceClass = createMateriKelasKuliah();
    $materi = $sourceClass->materis()->create([
        'judul_materi' => 'Materi Sumber',
        'pertemuan_ke' => 1,
        'jenis' => 'Materi',
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.kelas-kuliah.materi.duplicate', [$sourceClass, $materi]), ['target_ids' => [$sourceClass->id]])
        ->assertNotFound();

    expect($sourceClass->materis()->count())->toBe(1);
});
