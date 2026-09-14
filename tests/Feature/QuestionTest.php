<?php

use App\Models\Krs;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Role;

it('shows questions below quiz detail', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $kelas = createMateriKelasKuliah();
    $quiz = $kelas->quizzes()->create([
        'nama_quiz' => 'Quiz Soal',
        'uploaded_by' => $admin->id,
    ]);
    $quiz->questions()->create([
        'question_text' => 'Apa kepanjangan CPU?',
        'question_type' => 'single_choice',
        'question_option' => [
            ['text' => 'A', 'is_correct' => true],
            ['text' => 'B', 'is_correct' => false],
            ['text' => 'C', 'is_correct' => false],
        ],
        'points' => 10,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.kelas-kuliah.quiz.show', [$kelas, $quiz]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/QuizShow')
            ->has('quiz.questions', 1)
            ->where('quiz.questions.0.question_text', 'Apa kepanjangan CPU?')
            ->where('quiz.questions.0.question_type', 'single_choice')
            ->where('quiz.questions.0.points', 10));
});

it('shows quiz detail to enrolled students', function () {
    $mahasiswa = User::factory()->create(['role' => Role::Mahasiswa]);
    $kelas = createMateriKelasKuliah();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $quiz = $kelas->quizzes()->create([
        'nama_quiz' => 'Quiz Mahasiswa',
        'waktu_pengerjaan' => 30,
        'tenggat_waktu' => now()->addDay(),
        'uploaded_by' => $kelas->dosen->user_id,
    ]);
    $quiz->questions()->create(['question_text' => 'Soal?', 'question_type' => 'essay', 'points' => 10]);

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.quiz.show', $quiz))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Mahasiswa/QuizShow')
            ->where('quiz.nama_quiz', 'Quiz Mahasiswa')
            ->where('quiz.waktu_pengerjaan', 30)
            ->has('quiz.questions', 1));
});

it('rejects quiz detail for students outside the class', function () {
    $mahasiswa = User::factory()->create(['role' => Role::Mahasiswa]);
    $kelas = createMateriKelasKuliah();
    $quiz = $kelas->quizzes()->create([
        'nama_quiz' => 'Quiz Terbatas',
        'uploaded_by' => $kelas->dosen->user_id,
    ]);

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.quiz.show', $quiz))
        ->assertForbidden();
});

it('starts and submits a quiz attempt once', function () {
    $mahasiswa = User::factory()->create(['role' => Role::Mahasiswa]);
    $kelas = createMateriKelasKuliah();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $quiz = $kelas->quizzes()->create(['nama_quiz' => 'Quiz Submit', 'waktu_pengerjaan' => 30, 'uploaded_by' => $kelas->dosen->user_id]);
    $question = $quiz->questions()->create([
        'question_text' => 'Pilih A',
        'question_type' => 'single_choice',
        'question_option' => [['text' => 'A', 'is_correct' => true], ['text' => 'B', 'is_correct' => false]],
        'points' => 10,
    ]);

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.start', $quiz))
        ->assertRedirect(route('mahasiswa.quiz.show', $quiz));

    $attempt = QuizAttempt::firstOrFail();
    expect($attempt->started_at)->not->toBeNull();

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$question->id => 'A']])
        ->assertRedirect(route('mahasiswa.quiz.show', $quiz));

    expect($attempt->fresh()->submitted_at)->not->toBeNull()
        ->and((float) $attempt->fresh()->score)->toBe(10.0)
        ->and(QuizAnswer::where('attempt_id', $attempt->id)->value('answer'))->toBe(['A']);

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.start', $quiz))
        ->assertSessionHas('error');
});

it('rejects manual submit after the time is up but accepts auto submit', function () {
    $mahasiswa = User::factory()->create(['role' => Role::Mahasiswa]);
    $kelas = createMateriKelasKuliah();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $quiz = $kelas->quizzes()->create(['nama_quiz' => 'Quiz Habis', 'waktu_pengerjaan' => 30, 'uploaded_by' => $kelas->dosen->user_id]);
    $question = $quiz->questions()->create([
        'question_text' => 'Pilih A',
        'question_type' => 'single_choice',
        'question_option' => [['text' => 'A', 'is_correct' => true], ['text' => 'B', 'is_correct' => false]],
        'points' => 10,
    ]);
    $attempt = QuizAttempt::create([
        'quiz_id' => $quiz->id,
        'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id,
        'started_at' => now()->subHour(),
    ]);

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$question->id => 'A']])
        ->assertSessionHas('error');

    expect($attempt->fresh()->submitted_at)->toBeNull();

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$question->id => 'A'], 'auto_submit' => true])
        ->assertSessionHas('success');

    expect($attempt->fresh()->submitted_at)->not->toBeNull();
});

it('stores multiple questions at once', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $kelas = createMateriKelasKuliah();
    $quiz = $kelas->quizzes()->create([
        'nama_quiz' => 'Quiz Bulk',
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.kelas-kuliah.quiz.questions.store', [$kelas, $quiz]), [
            'questions' => [
                [
                    'question_text' => 'Pilih satu jawaban benar',
                    'question_type' => 'single_choice',
                    'question_option' => [
                        ['text' => 'A', 'is_correct' => true],
                        ['text' => 'B', 'is_correct' => false],
                        ['text' => 'C', 'is_correct' => false],
                    ],
                    'points' => 5,
                ],
                [
                    'question_text' => 'Jelaskan konsep OOP',
                    'question_type' => 'essay',
                    'question_option' => null,
                    'points' => 15,
                ],
                [
                    'question_text' => 'PHP adalah bahasa pemrograman?',
                    'question_type' => 'true_false',
                    'question_option' => [
                        ['text' => 'True', 'is_correct' => true],
                        ['text' => 'False', 'is_correct' => false],
                    ],
                    'points' => 2,
                ],
            ],
        ])
        ->assertRedirect(route('admin.kelas-kuliah.quiz.show', [$kelas, $quiz]));

    expect($quiz->questions()->count())->toBe(3)
        ->and($quiz->questions()->where('question_type', 'essay')->first()->question_option)->toBeNull()
        ->and($quiz->questions()->where('question_type', 'true_false')->first()->question_option)->toBe([
            ['text' => 'True', 'is_correct' => true],
            ['text' => 'False', 'is_correct' => false],
        ]);
});

it('rejects bulk questions from another class', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $kelas = createMateriKelasKuliah();
    $otherKelas = createMateriKelasKuliah();
    $quiz = $otherKelas->quizzes()->create([
        'nama_quiz' => 'Quiz Lain',
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.kelas-kuliah.quiz.questions.store', [$kelas, $quiz]), [
            'questions' => [
                [
                    'question_text' => 'Soal invalid',
                    'question_type' => 'essay',
                    'points' => 5,
                ],
            ],
        ])
        ->assertNotFound();
});
