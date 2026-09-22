<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;

it('stores quiz with duration and deadline under a class', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();

    $this->actingAs($admin)->post(route('admin.kelas-kuliah.quiz.store', $kelas), [
        'nama_quiz' => 'Quiz 1',
        'waktu_pengerjaan' => 60,
        'tenggat_waktu' => '2026-09-20 10:00:00',
        'catatan' => 'Kerjakan dengan jujur.',
    ])->assertRedirect()->assertSessionHas('quiz_success', 'Quiz berhasil ditambahkan.');

    $quiz = Quiz::firstOrFail();
    expect($quiz->nama_quiz)->toBe('Quiz 1')
        ->and($quiz->waktu_pengerjaan)->toBe(60)
        ->and($quiz->catatan)->toBe('Kerjakan dengan jujur.')
        ->and($quiz->kelas_id)->toBe($kelas->id)
        ->and($quiz->uploaded_by)->toBe($admin->id);
    expect($quiz->tenggat_waktu?->format('Y-m-d H:i'))->toBe('2026-09-20 10:00');
});

it('shows quiz detail scoped to its class', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();
    $quiz = $kelas->quizzes()->create([
        'nama_quiz' => 'Quiz Detail',
        'waktu_pengerjaan' => 45,
        'tenggat_waktu' => '2026-09-21 09:00:00',
        'catatan' => 'Detail quiz.',
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.kelas-kuliah.quiz.show', [$kelas, $quiz]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/QuizShow')
            ->where('quiz.id', $quiz->id)
            ->where('quiz.nama_quiz', 'Quiz Detail')
            ->where('kelasKuliah.id', $kelas->id));
});

it('shows quiz attempts and scores to admin', function () {
    $admin = User::factory()->admin()->create();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $kelas = createMateriKelasKuliah();
    $quiz = $kelas->quizzes()->create([
        'nama_quiz' => 'Quiz Attempt',
        'uploaded_by' => $admin->id,
    ]);
    $attempt = QuizAttempt::create([
        'quiz_id' => $quiz->id,
        'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id,
        'started_at' => now()->subMinutes(10),
        'submitted_at' => now(),
        'score' => 90,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.kelas-kuliah.quiz.show', [$kelas, $quiz]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quiz.attempts.0.id', $attempt->id)
            ->where('quiz.attempts.0.score', '90.00')
            ->where('quiz.attempts.0.mahasiswa.user.name', $mahasiswa->name));
});

it('rejects quiz detail from another class', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();
    $otherKelas = createMateriKelasKuliah();
    $quiz = $otherKelas->quizzes()->create([
        'nama_quiz' => 'Quiz Lain',
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.kelas-kuliah.quiz.show', [$kelas, $quiz]))
        ->assertNotFound();
});

it('allows nullable quiz notes, duration, and deadline', function () {
    $admin = User::factory()->admin()->create();
    $kelas = createMateriKelasKuliah();

    $this->actingAs($admin)->post(route('admin.kelas-kuliah.quiz.store', $kelas), [
        'nama_quiz' => 'Quiz 2',
    ])->assertRedirect()->assertSessionHas('quiz_success', 'Quiz berhasil ditambahkan.');

    $quiz = Quiz::where('nama_quiz', 'Quiz 2')->firstOrFail();
    expect($quiz->catatan)->toBeNull()
        ->and($quiz->waktu_pengerjaan)->toBeNull()
        ->and($quiz->tenggat_waktu)->toBeNull();
});
