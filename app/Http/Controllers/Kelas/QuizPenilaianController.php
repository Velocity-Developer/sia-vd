<?php

namespace App\Http\Controllers\Kelas;

use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Detail jawaban satu attempt quiz dan koreksi soal esai, dipakai oleh admin dan dosen pengampu.
 */
class QuizPenilaianController extends Controller
{
    use KontenKelas;

    public function show(Request $request, KelasKuliah $kelasKuliah, Quiz $quiz, QuizAttempt $attempt): Response
    {
        $this->ensureAccess($request, $kelasKuliah, $quiz, $attempt);
        $kelasKuliah->load('mataKuliah:id,nama_matkul');
        $attempt->load(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name', 'answers']);
        $answers = $attempt->answers->keyBy('question_id');

        return Inertia::render('Kelas/QuizPenilaian', [
            'kelasKuliah' => ['id' => $kelasKuliah->id, 'kode_kelas' => $kelasKuliah->kode_kelas, 'nama_matkul' => $kelasKuliah->mataKuliah?->nama_matkul],
            'quiz' => $quiz->only(['id', 'nama_quiz']),
            'attempt' => [
                'id' => $attempt->id,
                'mahasiswa' => $attempt->mahasiswa?->user?->name,
                'nim' => $attempt->mahasiswa?->nim,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'score' => $attempt->score,
                'auto_closed' => $attempt->auto_closed,
            ],
            'items' => $quiz->questions()->orderBy('id')->get()->map(fn (Question $question): array => [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'points' => $question->points,
                'options' => $question->question_option ?? [],
                'answer' => $answers->get($question->id)?->answer,
                'point' => $answers->get($question->id)?->point,
            ])->values(),
            'urls' => [
                'grade' => route($this->rute('kelas-kuliah.quiz.attempts.grade'), [$kelasKuliah, $quiz, $attempt]),
                'back' => route($this->rute('kelas-kuliah.quiz.show'), [$kelasKuliah, $quiz]),
            ],
            'breadcrumbKelas' => route($this->rute('kelas-kuliah.show'), $kelasKuliah),
            'nilaiTerkunci' => $this->pesanNilaiTerkunci($kelasKuliah, $quiz->ujian),
        ]);
    }

    public function grade(Request $request, KelasKuliah $kelasKuliah, Quiz $quiz, QuizAttempt $attempt): RedirectResponse
    {
        $this->ensureAccess($request, $kelasKuliah, $quiz, $attempt);
        $this->pastikanNilaiTidakTerkunci($kelasKuliah, $quiz->ujian);
        abort_if($attempt->submitted_at === null, 422, 'Quiz belum dikirim mahasiswa.');

        $essays = $quiz->questions()->where('question_type', 'essay')->get()->keyBy('id');
        $rules = ['points' => ['required', 'array']];

        foreach ($essays as $question) {
            $rules['points.'.$question->id] = ['nullable', 'numeric', 'min:0', 'max:'.$question->points];
        }

        $data = $request->validate($rules, [
            'numeric' => 'Poin harus berupa angka.',
            'min' => 'Poin minimal :min.',
            'max' => 'Poin maksimal :max.',
        ]);

        DB::transaction(function () use ($data, $essays, $attempt): void {
            foreach ($data['points'] as $questionId => $point) {
                if (! $essays->has($questionId)) {
                    continue;
                }

                QuizAnswer::query()->updateOrCreate(
                    ['attempt_id' => $attempt->id, 'question_id' => $questionId],
                    ['point' => $point === null ? null : (float) $point],
                );
            }

            $attempt->recalculateScore();
        });

        return back()->with('success', 'Nilai esai berhasil disimpan.');
    }

    private function ensureAccess(Request $request, KelasKuliah $kelasKuliah, Quiz $quiz, QuizAttempt $attempt): void
    {
        abort_unless($quiz->kelas_id === $kelasKuliah->id && $attempt->quiz_id === $quiz->id, 404);
        $this->pastikanAksesKelas($kelasKuliah);
    }
}
