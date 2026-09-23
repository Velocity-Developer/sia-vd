<?php

namespace App\Http\Controllers\Kelas;

use App\Http\Controllers\Concerns\FilterKelasKuliah;
use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class QuizController extends Controller
{
    use FilterKelasKuliah, KontenKelas;

    /**
     * Daftar quiz lintas kelas dengan filter; dosen hanya melihat kelas yang diampunya.
     */
    public function index(Request $request): Response
    {
        $filter = $this->filterKelas($request);

        $quizzes = Quiz::query()
            ->with(['kelasKuliah:id,kode_kelas,matkul_id,dosen_id,tahun_akademik_id', 'kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul', 'kelasKuliah.dosen:id,user_id', 'kelasKuliah.dosen.user:id,name', 'kelasKuliah.tahunAkademik:id,tahun,semester', 'uploader:id,name'])
            ->withCount(['questions', 'attempts'])
            ->tap(fn ($query) => $this->terapkanFilterKelas($query, $filter))
            ->when($filter['search'] !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('nama_quiz', 'like', "%{$filter['search']}%")
                ->orWhereHas('kelasKuliah', fn ($kelas) => $kelas->where('kode_kelas', 'like', "%{$filter['search']}%"))))

            ->orderByDesc('tenggat_waktu')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Kelas/QuizIndex', [
            'quizzes' => $quizzes,
            ...$this->propsFilterKelas($filter),
        ]);
    }

    public function create(KelasKuliah $kelasKuliah): Response
    {
        $this->pastikanAksesKelas($kelasKuliah);
        $kelasKuliah->load(['mataKuliah', 'dosen.user']);

        return Inertia::render('Kelas/QuizForm', [
            'peran' => $this->peran(),
            'kelasKuliah' => $kelasKuliah,
            'quiz' => null,
        ]);
    }

    public function store(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $this->pastikanAksesKelas($kelasKuliah);
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());
        $data['kelas_id'] = $kelasKuliah->id;
        $data['uploaded_by'] = $request->user()->id;
        Quiz::create($data);

        return $this->keKelas($kelasKuliah)->with('quiz_success', 'Quiz berhasil ditambahkan.');
    }

    public function show(KelasKuliah $kelasKuliah, Quiz $quiz): Response
    {
        $this->ensureScoped($kelasKuliah, $quiz);
        $kelasKuliah->load(['mataKuliah', 'dosen.user']);
        $quiz->load([
            'uploader:id,name',
            'questions',
            'attempts' => fn ($query) => $query->withExists(['answers as essay_belum_dinilai' => fn ($answers) => $answers
                ->whereNull('point')
                ->whereHas('question', fn ($question) => $question->where('question_type', 'essay'))]),
            'attempts.mahasiswa:id,user_id,nim',
            'attempts.mahasiswa.user:id,name',
        ]);

        return Inertia::render('Kelas/QuizShow', [
            'peran' => $this->peran(),
            'kelasKuliah' => $kelasKuliah,
            'quiz' => $quiz,
        ]);
    }

    public function edit(KelasKuliah $kelasKuliah, Quiz $quiz): Response
    {
        $this->ensureScoped($kelasKuliah, $quiz);
        $kelasKuliah->load(['mataKuliah', 'dosen.user']);

        return Inertia::render('Kelas/QuizForm', [
            'peran' => $this->peran(),
            'kelasKuliah' => $kelasKuliah,
            'quiz' => $quiz,
        ]);
    }

    public function update(Request $request, KelasKuliah $kelasKuliah, Quiz $quiz): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $quiz);
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());
        $quiz->update($data);

        return $this->keKelas($kelasKuliah)->with('quiz_success', 'Quiz berhasil diperbarui.');
    }

    public function storeQuestions(Request $request, KelasKuliah $kelasKuliah, Quiz $quiz): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $quiz);

        $validated = $request->validate([
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string'],
            'questions.*.question_type' => ['required', 'string', 'in:single_choice,multiple_choice,true_false,essay'],
            'questions.*.question_option' => ['nullable', 'array'],
            'questions.*.question_option.*.text' => ['required', 'string', 'max:255'],
            'questions.*.question_option.*.is_correct' => ['required', 'boolean'],
            'questions.*.points' => ['required', 'integer', 'min:0', 'max:1000'],
        ], [
            'questions.required' => 'Minimal satu question wajib diisi.',
            'questions.min' => 'Minimal satu question wajib diisi.',
            'questions.*.question_type.in' => 'Tipe pertanyaan tidak valid.',
            'questions.*.points.integer' => 'Poin harus berupa angka.',
            'questions.*.points.min' => 'Poin minimal :min.',
        ], [
            'questions' => 'Question',
            'questions.*.question_text' => 'Teks pertanyaan',
            'questions.*.question_type' => 'Tipe pertanyaan',
            'questions.*.question_option' => 'Opsi pertanyaan',
            'questions.*.points' => 'Poin',
        ]);

        foreach ($validated['questions'] as $index => $item) {
            $type = $item['question_type'];
            if (! $type) {
                throw ValidationException::withMessages([
                    "questions.{$index}.question_type" => 'Tipe pertanyaan wajib dipilih.',
                ]);
            }
            $options = $item['question_option'] ?? [];
            $hasFilledOption = collect($options)->contains(fn (array $option): bool => trim($option['text'] ?? '') !== '');
            if ($type !== 'essay' && ! $hasFilledOption) {
                throw ValidationException::withMessages([
                    "questions.{$index}.question_option" => 'Opsi jawaban wajib diisi.',
                ]);
            }
            if ($type !== 'essay' && ! collect($options)->contains(fn (array $option): bool => (bool) ($option['is_correct'] ?? false) && trim($option['text'] ?? '') !== '')) {
                throw ValidationException::withMessages([
                    "questions.{$index}.correct_answer" => 'Jawaban belum dipilih.',
                ]);
            }
            $options = $item['question_option'] ?? null;

            if ($type === 'true_false') {
                $options ??= [];
                $options = [
                    ['text' => 'True', 'is_correct' => (bool) ($options[0]['is_correct'] ?? false)],
                    ['text' => 'False', 'is_correct' => (bool) ($options[1]['is_correct'] ?? false)],
                ];
            }

            if ($type === 'essay') {
                $options = null;
            }

            if (is_array($options)) {
                $options = array_values(array_filter(array_map(
                    fn (array $option): array => ['text' => trim($option['text']), 'is_correct' => (bool) $option['is_correct']],
                    $options,
                ), fn (array $option): bool => $option['text'] !== ''));
                $options = $options === [] ? null : $options;
            }

            $quiz->questions()->create([
                'question_text' => $item['question_text'],
                'question_type' => $type,
                'question_option' => $options,
                'points' => $item['points'],
            ]);
        }

        return to_route($this->rute('kelas-kuliah.quiz.show'), [$kelasKuliah, $quiz])->with('question_success', 'Pertanyaan berhasil ditambahkan.');
    }

    public function updateQuestion(Request $request, KelasKuliah $kelasKuliah, Quiz $quiz, Question $question): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $quiz);
        // Mengubah atau menghapus soal menilai ulang attempt yang sudah dikirim.
        $this->pastikanNilaiTidakTerkunci($kelasKuliah);
        abort_unless($question->quiz_id === $quiz->id, 404);

        $data = $request->validate([
            'question_text' => ['required', 'string'],
            'question_type' => ['required', 'string', 'in:single_choice,multiple_choice,true_false,essay'],
            'question_option' => ['required_unless:question_type,essay', 'array', 'min:1'],
            'question_option.*.text' => ['required', 'string', 'max:255'],
            'question_option.*.is_correct' => ['required', 'boolean'],
            'points' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        if ($data['question_type'] !== 'essay' && empty($data['question_option'])) {
            throw ValidationException::withMessages([
                'question_option' => 'Opsi jawaban wajib diisi.',
            ]);
        }
        if ($data['question_type'] !== 'essay' && ! collect($data['question_option'] ?? [])->contains(fn (array $option): bool => (bool) ($option['is_correct'] ?? false))) {
            throw ValidationException::withMessages([
                'correct_answer' => 'Pilih minimal satu jawaban benar.',
            ]);
        }

        if ($data['question_type'] === 'true_false') {
            $options = $data['question_option'] ?? [];
            $data['question_option'] = [
                ['text' => 'True', 'is_correct' => (bool) ($options[0]['is_correct'] ?? false)],
                ['text' => 'False', 'is_correct' => (bool) ($options[1]['is_correct'] ?? false)],
            ];
        } elseif ($data['question_type'] === 'essay') {
            $data['question_option'] = null;
        } else {
            $data['question_option'] = collect($data['question_option'])
                ->map(fn (array $option): array => ['text' => trim($option['text']), 'is_correct' => (bool) $option['is_correct']])
                ->values()
                ->all();
        }

        $jadiEsai = $data['question_type'] === 'essay' && $question->question_type !== 'essay';
        $question->update($data);

        // Soal yang berubah menjadi esai harus dikoreksi dosen; poin otomatis dari jenis soal lama dibuang.
        if ($jadiEsai) {
            QuizAnswer::query()->where('question_id', $question->id)->update(['point' => null]);
        }

        $quiz->regradeAttempts();

        return to_route($this->rute('kelas-kuliah.quiz.show'), [$kelasKuliah, $quiz])->with('question_success', 'Pertanyaan berhasil diperbarui.');
    }

    public function destroyQuestion(KelasKuliah $kelasKuliah, Quiz $quiz, Question $question): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $quiz);
        // Mengubah atau menghapus soal menilai ulang attempt yang sudah dikirim.
        $this->pastikanNilaiTidakTerkunci($kelasKuliah);
        abort_unless($question->quiz_id === $quiz->id, 404);
        $question->delete();
        $quiz->regradeAttempts();

        return to_route($this->rute('kelas-kuliah.quiz.show'), [$kelasKuliah, $quiz])->with('question_success', 'Pertanyaan berhasil dihapus.');
    }

    public function duplicate(Request $request, KelasKuliah $kelasKuliah, Quiz $quiz): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $quiz);
        $targets = $this->kelasTujuanDuplikasi($request, $kelasKuliah);
        $quiz->load('questions');

        foreach ($targets as $target) {
            $copy = $quiz->replicate();
            $copy->kelas_id = $target->id;
            $copy->uploaded_by = $request->user()->id;
            $copy->save();

            foreach ($quiz->questions as $question) {
                $copy->questions()->create($question->only(['question_text', 'question_type', 'question_option', 'points']));
            }
        }

        return $this->keKelas($kelasKuliah)->with('quiz_success', 'Quiz berhasil diduplikasi ke: '.$targets->map(fn (KelasKuliah $target): string => $target->kode_kelas)->join(', ').'.');
    }

    public function destroy(KelasKuliah $kelasKuliah, Quiz $quiz): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $quiz);

        try {
            $quiz->delete();
        } catch (Throwable) {
            return $this->keKelas($kelasKuliah)->with('quiz_error', 'Quiz gagal dihapus.');
        }

        return $this->keKelas($kelasKuliah)->with('quiz_success', 'Quiz berhasil dihapus.');
    }

    private function ensureScoped(KelasKuliah $kelasKuliah, Quiz $quiz): void
    {
        $this->pastikanAksesKelas($kelasKuliah);
        abort_unless($quiz->kelas_id === $kelasKuliah->id, 404);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'nama_quiz' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            'waktu_pengerjaan' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'tenggat_waktu' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'nama_quiz' => 'Nama Quiz',
            'catatan' => 'Catatan',
            'waktu_pengerjaan' => 'Waktu Pengerjaan',
            'tenggat_waktu' => 'Tenggat Waktu',
        ];
    }
}
