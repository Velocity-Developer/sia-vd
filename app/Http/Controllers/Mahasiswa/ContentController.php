<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Materi;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    public function jadwalKuliah(Request $request): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        $kelasKuliahs = Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereHas('kelasKuliah.tahunAkademik', fn ($query) => $query->where('status', true))
            ->with('kelasKuliah.mataKuliah', 'kelasKuliah.jadwals.ruang')
            ->get()
            ->pluck('kelasKuliah')
            ->filter()
            ->values();

        return Inertia::render('Mahasiswa/JadwalKuliah', ['kelasKuliahs' => $kelasKuliahs]);
    }

    public function show(Request $request, KelasKuliah $kelasKuliah): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        abort_unless($kelasKuliah->krs()->where('mahasiswa_id', $mahasiswa->id)->exists(), 403);

        $kelasKuliah->load([
            'mataKuliah.prodi.fakultas',
            'tahunAkademik',
            'dosen.user',
            'jadwals.ruang',
            'materis.uploader:id,name',
            'tugas.uploader:id,name',
            'quizzes.uploader:id,name',
        ]);

        return Inertia::render('Mahasiswa/KelasKuliahShow', ['kelasKuliah' => $kelasKuliah]);
    }

    public function materiShow(Request $request, Materi $materi): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        abort_unless($materi->kelasKuliah()->whereHas('krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id))->exists(), 403);

        $materi->load(['kelasKuliah.mataKuliah', 'uploader:id,name']);

        return Inertia::render('Mahasiswa/MateriShow', ['materi' => $materi]);
    }

    public function quizShow(Request $request, Quiz $quiz): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        abort_unless($quiz->kelasKuliah()->whereHas('krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id))->exists(), 403);

        $quiz->load(['kelasKuliah.mataKuliah', 'uploader:id,name']);
        $attempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->with('answers')
            ->first();

        $attempt?->setRelation('quiz', $quiz)->closeIfExpired();
        $deadline = $attempt?->submitted_at === null ? $attempt?->deadline() : null;
        $attempt?->unsetRelation('quiz');

        // Soal hanya dikirim selama attempt berjalan, agar tidak bisa dibaca sebelum quiz dimulai.
        if ($attempt !== null && $attempt->submitted_at === null) {
            $quiz->load('questions');
        } else {
            $quiz->setRelation('questions', new EloquentCollection);
        }

        $quiz->questions->each(function ($question): void {
            $question->question_option = collect($question->question_option ?? [])
                ->map(fn (array $option): array => ['text' => $option['text'] ?? ''])
                ->all();
        });

        return Inertia::render('Mahasiswa/QuizShow', [
            'quiz' => $quiz,
            'attempt' => $attempt,
            // Hitung mundur di browser memakai jam server, bukan jam perangkat mahasiswa.
            'deadline' => $deadline?->toIso8601String(),
            'serverNow' => now()->toIso8601String(),
            'essayBelumDinilai' => $attempt?->submitted_at !== null && $attempt->hasUngradedEssay(),
        ]);
    }
}
