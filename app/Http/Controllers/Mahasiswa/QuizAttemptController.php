<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Ujian;
use Illuminate\Http\JsonResponse;
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

        // Lembar soal ujian: hanya selama jam ujian dan bagi yang memenuhi syarat kehadiran.
        if ($ujian = $quiz->ujian) {
            $tolak = match (true) {
                $ujian->status !== Ujian::TERBIT => 'Ujian belum diterbitkan.',
                ! $ujian->sudahMulai() => 'Ujian belum dimulai.',
                $ujian->sudahSelesai() => 'Waktu ujian sudah habis.',
                ! $ujian->bolehIkut($mahasiswa->id) => $ujian->remidi() ? 'Anda bukan peserta remidi yang tagihannya lunas.' : 'Anda belum memenuhi syarat kehadiran untuk mengikuti ujian ini.',
                default => null,
            };

            if ($tolak !== null) {
                return to_route('mahasiswa.ujian.show', $ujian)->with('error', $tolak);
            }
        }

        if ($quiz->tenggat_waktu?->isPast()) {
            return to_route('mahasiswa.quiz.show', $quiz)->with('error', 'Tenggat quiz telah berakhir.');
        }

        $attempt = QuizAttempt::createOrFirst(
            ['quiz_id' => $quiz->id, 'mahasiswa_id' => $mahasiswa->id],
            ['started_at' => now()],
        );
        $attempt->setRelation('quiz', $quiz)->closeIfExpired();

        if ($attempt->submitted_at !== null) {
            return to_route('mahasiswa.quiz.show', $quiz)->with('error', 'Quiz sudah pernah dikerjakan.');
        }

        $quiz->ujian?->catatHadir($mahasiswa->id);

        return to_route('mahasiswa.quiz.show', $quiz)->with('success', $quiz->ujian ? 'Ujian dimulai. Kerjakan sebelum waktu habis.' : 'Quiz dimulai.');
    }

    /**
     * Simpan otomatis jawaban selama quiz berjalan (dipanggil berkala dari halaman quiz).
     */
    public function saveAnswers(Request $request, Quiz $quiz): JsonResponse
    {
        $jawaban = $this->validatedAnswers($request, $quiz);

        // Attempt dikunci selama disimpan, agar simpan otomatis yang terlambat tiba tidak menimpa
        // attempt yang sudah dikirim atau ditutup.
        $tertutup = DB::transaction(function () use ($request, $quiz, $jawaban): bool {
            $attempt = $this->currentAttempt($request, $quiz, terkunci: true);

            if ($attempt->submitted_at !== null || $attempt->closeIfExpired()) {
                return true;
            }

            $attempt->saveDraft($jawaban);

            return false;
        });

        return response()->json(
            $tertutup ? ['closed' => true] : ['closed' => false, 'saved_at' => now()->toIso8601String()],
            $tertutup ? 409 : 200,
        );
    }

    public function submit(Request $request, Quiz $quiz): RedirectResponse
    {
        $request->validate(['auto_submit' => ['sometimes', 'boolean']]);
        $jawaban = $this->validatedAnswers($request, $quiz);

        // Satu attempt hanya boleh dinilai sekali: baris dikunci dan statusnya dicek ulang di dalam kunci,
        // sehingga dua kiriman bersamaan (atau kiriman yang beradu dengan penutupan otomatis) tidak saling menimpa.
        $error = DB::transaction(function () use ($request, $quiz, $jawaban): ?string {
            $attempt = $this->currentAttempt($request, $quiz, terkunci: true);

            if ($attempt->submitted_at !== null) {
                return 'Quiz sudah pernah dikirim.';
            }

            // Batas waktu selalu dicek di server. Kiriman yang terlambat diabaikan; yang dinilai adalah
            // jawaban terakhir yang tersimpan otomatis sebelum waktu habis.
            if ($attempt->closeIfExpired()) {
                return 'Waktu quiz telah habis. Jawaban terakhir yang tersimpan otomatis sudah dinilai.';
            }

            $attempt->finalize($jawaban);

            return null;
        });

        if ($error !== null) {
            return to_route('mahasiswa.quiz.show', $quiz)->with('error', $error);
        }

        $message = $request->boolean('auto_submit')
            ? 'Waktu quiz telah habis. Jawaban yang sudah Anda isi berhasil terkirim.'
            : 'Quiz berhasil ter-submit.';

        return to_route('mahasiswa.quiz.show', $quiz)->with('success', $message);
    }

    private function currentAttempt(Request $request, Quiz $quiz, bool $terkunci = false): QuizAttempt
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);
        $this->ensureAccess($quiz, $mahasiswa->id);

        return QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->when($terkunci, fn ($query) => $query->lockForUpdate())
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
