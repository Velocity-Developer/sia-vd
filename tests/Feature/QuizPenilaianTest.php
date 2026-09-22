<?php

use App\Models\Krs;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;

function submittedEssayAttempt(): array
{
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $quiz = $kelas->quizzes()->create(['nama_quiz' => 'Quiz Esai', 'uploaded_by' => $kelas->dosen->user_id]);
    $pilihan = $quiz->questions()->create([
        'question_text' => 'Pilih A',
        'question_type' => 'single_choice',
        'question_option' => [['text' => 'A', 'is_correct' => true], ['text' => 'B', 'is_correct' => false]],
        'points' => 10,
    ]);
    $esai = $quiz->questions()->create(['question_text' => 'Jelaskan', 'question_type' => 'essay', 'points' => 20]);
    $attempt = QuizAttempt::create(['quiz_id' => $quiz->id, 'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'started_at' => now()]);
    $attempt->setRelation('quiz', $quiz)->finalize([$pilihan->id => 'A', $esai->id => 'Jawaban esai']);

    return [$kelas, $mahasiswa, $quiz, $pilihan, $esai, $attempt->fresh()];
}

it('grades an essay and adds it to the score', function () {
    [$kelas, $mahasiswa, $quiz, , $esai, $attempt] = submittedEssayAttempt();
    expect((float) $attempt->score)->toBe(10.0);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.quiz.show', $quiz))
        ->assertInertia(fn ($page) => $page->where('essayBelumDinilai', true));

    $this->actingAs($kelas->dosen->user)
        ->get(route('dosen.kelas-kuliah.quiz.show', [$kelas, $quiz]))
        ->assertInertia(fn ($page) => $page->where('quiz.attempts.0.essay_belum_dinilai', true));

    $this->actingAs($kelas->dosen->user)
        ->get(route('dosen.kelas-kuliah.quiz.attempts.show', [$kelas, $quiz, $attempt]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Kelas/QuizPenilaian')->has('items', 2));

    $this->actingAs($kelas->dosen->user)
        ->put(route('dosen.kelas-kuliah.quiz.attempts.grade', [$kelas, $quiz, $attempt]), ['points' => [$esai->id => 15]])
        ->assertSessionHas('success');

    expect((float) $attempt->fresh()->score)->toBe(25.0);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.quiz.show', $quiz))
        ->assertInertia(fn ($page) => $page->where('essayBelumDinilai', false));
});

it('rejects essay points above the question maximum', function () {
    [$kelas, , $quiz, , $esai, $attempt] = submittedEssayAttempt();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.kelas-kuliah.quiz.attempts.grade', [$kelas, $quiz, $attempt]), ['points' => [$esai->id => 25]])
        ->assertSessionHasErrors('points.'.$esai->id);

    expect((float) $attempt->fresh()->score)->toBe(10.0);
});

it('does not let another dosen grade the attempt', function () {
    [$kelas, , $quiz, , $esai, $attempt] = submittedEssayAttempt();
    $dosenLain = User::factory()->dosen()->create();

    $this->actingAs($dosenLain)
        ->put(route('dosen.kelas-kuliah.quiz.attempts.grade', [$kelas, $quiz, $attempt]), ['points' => [$esai->id => 15]])
        ->assertForbidden();
});

it('regrades submitted attempts when the answer key is changed', function () {
    [$kelas, , $quiz, $pilihan, , $attempt] = submittedEssayAttempt();

    $this->actingAs($kelas->dosen->user)
        ->put(route('dosen.kelas-kuliah.quiz.questions.update', [$kelas, $quiz, $pilihan]), [
            'question_text' => 'Pilih B',
            'question_type' => 'single_choice',
            'question_option' => [['text' => 'A', 'is_correct' => '0'], ['text' => 'B', 'is_correct' => '1']],
            'points' => 10,
        ])
        ->assertSessionHasNoErrors();

    expect($pilihan->fresh()->question_option[1]['is_correct'])->toBeTrue()
        ->and((float) $attempt->fresh()->score)->toBe(0.0)
        ->and((float) QuizAnswer::where('attempt_id', $attempt->id)->where('question_id', $pilihan->id)->value('point'))->toBe(0.0);
});

it('stores a quiz score of 1000 points or more', function () {
    [$kelas, $mahasiswa, $quiz, $pilihan] = submittedEssayAttempt();
    $pilihan->update(['points' => 1000]);
    $quiz->regradeAttempts();

    expect((float) QuizAttempt::where('quiz_id', $quiz->id)->value('score'))->toBe(1000.0);
});
