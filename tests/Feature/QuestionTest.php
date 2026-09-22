<?php

use App\Models\Krs;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;

it('shows questions below quiz detail', function () {
    $admin = User::factory()->admin()->create();
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
            ->component('Kelas/QuizShow')
            ->has('quiz.questions', 1)
            ->where('quiz.questions.0.question_text', 'Apa kepanjangan CPU?')
            ->where('quiz.questions.0.question_type', 'single_choice')
            ->where('quiz.questions.0.points', 10));
});

it('shows quiz detail to enrolled students', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();
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
            ->has('quiz.questions', 0));

    QuizAttempt::create(['quiz_id' => $quiz->id, 'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'started_at' => now()]);

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.quiz.show', $quiz))
        ->assertInertia(fn ($page) => $page->has('quiz.questions', 1));
});

it('rejects quiz detail for students outside the class', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();
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
    $mahasiswa = User::factory()->mahasiswa()->create();
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

function createTimedQuizAttempt(array $quizAttributes, \DateTimeInterface $startedAt): array
{
    $mahasiswa = User::factory()->mahasiswa()->create();
    $kelas = createMateriKelasKuliah();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $quiz = $kelas->quizzes()->create(['nama_quiz' => 'Quiz Berwaktu', 'uploaded_by' => $kelas->dosen->user_id, ...$quizAttributes]);
    $question = $quiz->questions()->create([
        'question_text' => 'Pilih A',
        'question_type' => 'single_choice',
        'question_option' => [['text' => 'A', 'is_correct' => true], ['text' => 'B', 'is_correct' => false]],
        'points' => 10,
    ]);
    $attempt = QuizAttempt::create([
        'quiz_id' => $quiz->id,
        'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id,
        'started_at' => $startedAt,
    ]);

    return [$mahasiswa, $quiz, $question, $attempt];
}

it('accepts a submit that arrives within the grace period after the time is up', function () {
    [$mahasiswa, $quiz, $question, $attempt] = createTimedQuizAttempt(['waktu_pengerjaan' => 30], now()->subMinutes(30)->subSeconds(20));

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$question->id => 'A'], 'auto_submit' => true])
        ->assertSessionHas('success');

    expect((float) $attempt->fresh()->score)->toBe(10.0);
});

it('rejects a late submit even when it claims to be an auto submit', function () {
    [$mahasiswa, $quiz, $question, $attempt] = createTimedQuizAttempt(['waktu_pengerjaan' => 30], now()->subHour());

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$question->id => 'A'], 'auto_submit' => true])
        ->assertSessionHas('error');

    $attempt->refresh();
    expect($attempt->submitted_at)->not->toBeNull()
        ->and($attempt->auto_closed)->toBeTrue()
        ->and((float) $attempt->score)->toBe(0.0)
        ->and(QuizAnswer::where('attempt_id', $attempt->id)->value('answer'))->toBeNull();
});

it('grades the last autosaved answers when a late submit is rejected', function () {
    [$mahasiswa, $quiz, $question, $attempt] = createTimedQuizAttempt(['waktu_pengerjaan' => 30], now()->subMinutes(20));

    $this->actingAs($mahasiswa)
        ->postJson(route('mahasiswa.quiz.answers', $quiz), ['answers' => [$question->id => 'A']])
        ->assertOk()
        ->assertJson(['closed' => false]);

    $this->travel(15)->minutes();

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$question->id => 'B'], 'auto_submit' => true])
        ->assertSessionHas('error');

    $attempt->refresh();
    expect((float) $attempt->score)->toBe(10.0)
        ->and($attempt->auto_closed)->toBeTrue()
        ->and(QuizAnswer::where('attempt_id', $attempt->id)->value('answer'))->toBe(['A']);
});

it('reports a closed attempt to the autosave endpoint once time is up', function () {
    [$mahasiswa, $quiz, $question, $attempt] = createTimedQuizAttempt(['waktu_pengerjaan' => 30], now()->subHour());

    $this->actingAs($mahasiswa)
        ->postJson(route('mahasiswa.quiz.answers', $quiz), ['answers' => [$question->id => 'A']])
        ->assertStatus(409)
        ->assertJson(['closed' => true]);

    expect($attempt->fresh()->submitted_at)->not->toBeNull();
});

it('sends the attempt deadline and server time to the quiz page', function () {
    [$mahasiswa, $quiz, , $attempt] = createTimedQuizAttempt(['waktu_pengerjaan' => 30], now()->subMinutes(10));

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.quiz.show', $quiz))
        ->assertInertia(fn ($page) => $page
            ->where('deadline', $attempt->started_at->copy()->addMinutes(30)->toIso8601String())
            ->where('serverNow', fn (string $value): bool => abs(strtotime($value) - time()) < 5));
});

it('scores a multiple choice answer given as a list of options', function () {
    [$mahasiswa, $quiz, , $attempt] = createTimedQuizAttempt(['waktu_pengerjaan' => 30], now());
    $question = $quiz->questions()->create([
        'question_text' => 'Pilih bilangan prima',
        'question_type' => 'multiple_choice',
        'question_option' => [['text' => '2', 'is_correct' => true], ['text' => '4', 'is_correct' => false], ['text' => '5', 'is_correct' => true]],
        'points' => 20,
    ]);

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$question->id => ['5', '2']]])
        ->assertSessionHas('success');

    expect((float) $attempt->fresh()->score)->toBe(20.0);
});

it('rejects answers that are not one of the question options', function (mixed $answer, string $errorKey) {
    [$mahasiswa, $quiz, , $attempt] = createTimedQuizAttempt(['waktu_pengerjaan' => 30], now());
    $question = $quiz->questions()->create([
        'question_text' => 'Pilih bilangan prima',
        'question_type' => 'multiple_choice',
        'question_option' => [['text' => '2', 'is_correct' => true], ['text' => '4', 'is_correct' => false]],
        'points' => 20,
    ]);

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$question->id => $answer]])
        ->assertSessionHasErrors(sprintf($errorKey, $question->id));

    expect($attempt->fresh()->submitted_at)->toBeNull();
})->with([
    'boolean from an unbound checkbox' => [true, 'answers.%d'],
    'unknown option' => [['9'], 'answers.%d.0'],
]);

it('rejects a submit after the quiz deadline', function () {
    [$mahasiswa, $quiz, $question, $attempt] = createTimedQuizAttempt(['tenggat_waktu' => now()->subMinutes(10)], now()->subMinutes(20));

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.submit', $quiz), ['answers' => [$question->id => 'A']])
        ->assertSessionHas('error');

    expect((float) $attempt->fresh()->score)->toBe(0.0);
});

it('closes an expired attempt when the student reopens the quiz', function () {
    [$mahasiswa, $quiz, , $attempt] = createTimedQuizAttempt(['waktu_pengerjaan' => 30], now()->subHour());

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.quiz.show', $quiz))
        ->assertInertia(fn ($page) => $page->has('quiz.questions', 0)->where('attempt.score', '0.00'));

    expect($attempt->fresh()->submitted_at)->not->toBeNull();
});

it('stores multiple questions at once', function () {
    $admin = User::factory()->admin()->create();
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
    $admin = User::factory()->admin()->create();
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
