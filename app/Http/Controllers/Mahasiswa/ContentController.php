<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Materi;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Ujian;
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
            ->with([
                'kelasKuliah:id,kode_kelas,matkul_id',
                'kelasKuliah.mataKuliah:id,nama_matkul,sks',
                'kelasKuliah.jadwals:id,kelas_id,hari,jam_mulai,jam_akhir,ruang_id',
                'kelasKuliah.jadwals.ruang:id,kode_ruang,nama_ruang',
            ])
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
            'dosen:id,user_id',
            'dosen.user:id,name',
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
        // Lembar soal ujian hanya bisa dibuka bila jadwal ujiannya sudah terbit.
        $ujian = $quiz->ujian;
        abort_if($ujian !== null && $ujian->status !== Ujian::TERBIT, 404);

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

        // Ujian: urutan soal dan opsi diacak per mahasiswa (tetap sama selama ia mengerjakan). Jawaban
        // disimpan berdasarkan teks opsi, jadi pengacakan tidak memengaruhi penilaian.
        if ($ujian !== null && $attempt !== null) {
            $acak = fn (string $kunci): int => crc32($attempt->id.'-'.$kunci);
            $quiz->setRelation('questions', $quiz->questions->sortBy(fn ($q) => $acak('s'.$q->id))->values());
            $quiz->questions->each(function ($question) use ($acak): void {
                $question->question_option = collect($question->question_option)->sortBy(fn (array $o) => $acak('o'.$question->id.$o['text']))->values()->all();
            });
        }

        // Nilai ujian baru terlihat setelah dosen merilisnya.
        $sembunyikanNilai = $ujian !== null && ! $ujian->nilai_dirilis;
        if ($sembunyikanNilai && $attempt !== null) {
            $attempt->score = null;
            $attempt->setRelation('answers', $attempt->answers->each(fn ($answer) => $answer->point = null));
        }

        return Inertia::render('Mahasiswa/QuizShow', [
            'quiz' => $quiz,
            'attempt' => $attempt,
            // Hitung mundur di browser memakai jam server, bukan jam perangkat mahasiswa.
            'deadline' => $deadline?->toIso8601String(),
            'serverNow' => now()->toIso8601String(),
            'essayBelumDinilai' => ! $sembunyikanNilai && $attempt?->submitted_at !== null && $attempt->hasUngradedEssay(),
            'ujian' => $ujian ? ['id' => $ujian->id, 'jenis' => $ujian->jenis, 'nilai_dirilis' => $ujian->nilai_dirilis] : null,
        ]);
    }
}
