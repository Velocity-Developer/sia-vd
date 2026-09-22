<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        $attempt->setRelation('quiz', $quiz)->closeIfExpired();

        if ($attempt->submitted_at !== null) {
            return to_route('mahasiswa.quiz.show', $quiz)->with('error', 'Quiz sudah pernah dikerjakan.');
        }

        return to_route('mahasiswa.quiz.show', $quiz)->with('success', 'Quiz dimulai.');
    }

    /**
     * Simpan otomatis jawaban selama quiz berjalan (dipanggil berkala dari halaman quiz).
     */
    public function saveAnswers(Request $request, Quiz $quiz): JsonResponse
    {
        $attempt = $this->currentAttempt($request, $quiz);

        if ($attempt->submitted_at !== null || $attempt->closeIfExpired()) {
            return response()->json(['closed' => true], 409);
        }

        $attempt->saveDraft($this->validatedAnswers($request, $quiz));

        return response()->json(['closed' => false, 'saved_at' => now()->toIso8601String()]);
    }

    public function submit(Request $request, Quiz $quiz): RedirectResponse
    {
        $attempt = $this->currentAttempt($request, $quiz);

        if ($attempt->submitted_at !== null) {
            return to_route('mahasiswa.quiz.show', $quiz)->with('error', 'Quiz sudah pernah dikirim.');
        }

        // Batas waktu selalu dicek di server. Kiriman yang terlambat diabaikan; yang dinilai adalah
        // jawaban terakhir yang tersimpan otomatis sebelum waktu habis.
        if ($attempt->closeIfExpired()) {
            return to_route('mahasiswa.quiz.show', $quiz)->with('error', 'Waktu quiz telah habis. Jawaban terakhir yang tersimpan otomatis sudah dinilai.');
        }

        $request->validate(['auto_submit' => ['sometimes', 'boolean']]);
        $attempt->finalize($this->validatedAnswers($request, $quiz));

        $message = $request->boolean('auto_submit')
            ? 'Waktu quiz telah habis. Jawaban yang sudah Anda isi berhasil terkirim.'
            : 'Quiz berhasil ter-submit.';

        return to_route('mahasiswa.quiz.show', $quiz)->with('success', $message);
    }

    private function currentAttempt(Request $request, Quiz $quiz): QuizAttempt
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);
        $this->ensureAccess($quiz, $mahasiswa->id);

        return QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->firstOrFail()
            ->setRelation('quiz', $quiz);
    }

    /**
     * Validasi jawaban sesuai jenis soal: pilihan ganda harus berupa daftar opsi yang ada,
     * pilihan tunggal satu opsi yang ada, esai berupa teks.
     *
     * @return array<int|string, mixed>
     */
    private function validatedAnswers(Request $request, Quiz $quiz): array
    {
        $rules = ['answers' => ['nullable', 'array']];

        foreach ($quiz->questions()->get() as $question) {
            /** @var Question $question */
            $rules += $question->answerRules('answers.'.$question->id);
        }

        return $request->validate($rules, [
            'in' => 'Jawaban tidak sesuai dengan pilihan yang tersedia.',
            'array' => 'Format jawaban tidak valid.',
            'string' => 'Format jawaban tidak valid.',
        ])['answers'] ?? [];
    }

    private function ensureAccess(Quiz $quiz, int $mahasiswaId): void
    {
        abort_unless($quiz->kelasKuliah()->whereHas('krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswaId))->exists(), 403);
    }
}
