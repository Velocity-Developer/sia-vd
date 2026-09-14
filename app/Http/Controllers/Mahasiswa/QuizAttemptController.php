<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizAttemptController extends Controller
{
    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);
        $this->ensureAccess($quiz, $mahasiswa->id);

        if ($quiz->tenggat_waktu?->isPast()) {
            return to_route('mahasiswa.quiz.show', $quiz)->with('error', 'Tenggat quiz telah berakhir.');
        }

        $attempt = QuizAttempt::firstOrCreate(
            ['quiz_id' => $quiz->id, 'mahasiswa_id' => $mahasiswa->id],
            ['started_at' => now()],
        );

        if ($attempt->submitted_at !== null) {
            return to_route('mahasiswa.quiz.show', $quiz)->with('error', 'Quiz sudah pernah dikerjakan.');
        }

        return to_route('mahasiswa.quiz.show', $quiz)->with('success', 'Quiz dimulai.');
    }

    public function submit(Request $request, Quiz $quiz): RedirectResponse
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);
        $this->ensureAccess($quiz, $mahasiswa->id);

        $attempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->firstOrFail();

        if ($attempt->submitted_at !== null) {
            return to_route('mahasiswa.quiz.show', $quiz)->with('error', 'Quiz sudah pernah dikirim.');
        }

        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable'],
            'auto_submit' => ['sometimes', 'boolean'],
        ]);

        $expiresAt = $quiz->waktu_pengerjaan
            ? $attempt->started_at->copy()->addMinutes($quiz->waktu_pengerjaan)
            : null;

        $autoSubmit = $validated['auto_submit'] ?? false;

        if (! $autoSubmit && $expiresAt?->isPast()) {
            return to_route('mahasiswa.quiz.show', $quiz)->with('error', 'Waktu quiz telah habis. Jawaban dikirim otomatis.');
        }

        $quiz->load('questions');
        $answers = $validated['answers'] ?? [];
        $score = 0;

        DB::transaction(function () use ($attempt, $quiz, $answers, &$score): void {
            foreach ($quiz->questions as $question) {
                $answer = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;
                $point = $this->calculatePoint($question, $answer);

                if ($point !== null) {
                    $score += $point;
                }

                $attempt->answers()->updateOrCreate(
                    ['question_id' => $question->id],
                    [
                        'answer' => $answer === null ? null : (is_array($answer) ? array_values($answer) : [$answer]),
                        'point' => $point,
                    ],
                );
            }

            $attempt->update(['submitted_at' => now(), 'score' => $score]);
        });

        $message = $autoSubmit
            ? 'Waktu quiz telah habis. Jawaban yang sudah Anda isi berhasil terkirim.'
            : 'Quiz berhasil ter-submit.';

        return to_route('mahasiswa.quiz.show', $quiz)->with('success', $message);
    }

    private function ensureAccess(Quiz $quiz, int $mahasiswaId): void
    {
        abort_unless($quiz->kelasKuliah()->whereHas('krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswaId))->exists(), 403);
    }

    private function calculatePoint(Question $question, mixed $answer): ?float
    {
        if ($question->question_type === 'essay') {
            return null;
        }

        $correct = collect($question->question_option ?? [])
            ->filter(fn (array $option): bool => ($option['is_correct'] ?? false) === true)
            ->pluck('text')
            ->values()
            ->all();
        $submitted = is_array($answer) ? array_values($answer) : [$answer];
        sort($correct);
        sort($submitted);

        return $correct === $submitted ? (float) $question->points : 0.0;
    }
}
