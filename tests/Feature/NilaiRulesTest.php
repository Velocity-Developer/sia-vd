<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengumpulanTugas;
use App\Models\QuizAttempt;
use App\Models\TahunAkademik;
use App\Models\Tugas;
use App\Models\User;

it('lets a grade be cleared', function () {
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);

    isiNilaiKomponen($this, $kelas, $krs, 75)->assertSessionHas('success');
    expect($krs->fresh()->nilai)->toBe('B');

    isiNilaiKomponen($this, $kelas, $krs, null)->assertSessionHas('success');
    expect($krs->fresh()->nilai)->toBeNull();
});

it('does not accept a final letter typed directly outside remidi', function () {
    $kelas = createMateriKelasKuliah();
    $krs = Krs::create(['mahasiswa_id' => User::factory()->mahasiswa()->create()->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);

    $this->actingAs($kelas->dosen->user)->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'A'])->assertSessionHas('error');
    $this->actingAs(User::factory()->admin()->create())->put(route('admin.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'A'])->assertSessionHas('error');
    expect($krs->fresh()->nilai)->toBeNull();

    // Tanpa komponen nilai yang lengkap, halaman kelas menjelaskan kenapa nilai belum bisa diisi.
    $this->actingAs($kelas->dosen->user)->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('nilaiKomponen', null)->where('nilaiBelumSiap', fn ($alasan) => str_contains($alasan, 'Komponen nilai belum diatur')));
});

it('locks grades for dosen once the tahun akademik is no longer active, but not for admin', function () {
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'B']);
    $kelas->tahunAkademik->update(['status' => false]);

    isiNilaiKomponen($this, $kelas, $krs, 90)->assertSessionHas('error');
    expect($krs->fresh()->nilai)->toBe('B');

    $this->actingAs($kelas->dosen->user)
        ->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('nilaiTerkunci', true));

    isiNilaiKomponen($this, $kelas, $krs, 90, User::factory()->admin()->create())->assertSessionHas('success');
    expect($krs->fresh()->nilai)->toBe('A');
});

it('counts a retaken course once in the transcript using its best grade', function () {
    $kelas = createMateriKelasKuliah();
    $lalu = TahunAkademik::create(['tahun' => '2024/2025', 'semester' => 'Ganjil', 'tanggal_mulai' => '2024-08-01', 'tanggal_akhir' => '2025-01-31', 'tanggal_krs_awal' => '2024-08-01', 'tanggal_krs_akhir' => '2024-08-14', 'status' => false]);
    $kelasLalu = KelasKuliah::create(['kode_kelas' => 'LALU-A', 'tahun_akademik_id' => $lalu->id, 'kapasitas' => 30, 'dosen_id' => $kelas->dosen_id, 'matkul_id' => $kelas->matkul_id]);
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelasLalu->id, 'nilai' => 'E', 'nilai_divalidasi_at' => now()]);
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'B', 'nilai_divalidasi_at' => now()]);
    $sks = $kelas->mataKuliah->sks;

    $this->actingAs($mahasiswa)->get(route('mahasiswa.transkrip'))
        ->assertInertia(fn ($page) => $page
            ->has('transkrip', 1)
            ->where('transkrip.0.nilai', 'B')
            ->where('transkrip.0.diambil', 2)
            ->where('ringkasan.totalSks', $sks)
            ->where('ringkasan.totalSksLulus', $sks)
            ->where('ringkasan.ipk', 3));
});

it('locks essay grading, tugas grading, and question edits once the tahun akademik is inactive', function () {
    $kelas = createMateriKelasKuliah();
    $mahasiswa = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $quiz = $kelas->quizzes()->create(['nama_quiz' => 'Quiz', 'uploaded_by' => $kelas->dosen->user_id]);
    $soal = $quiz->questions()->create(['question_text' => 'Jelaskan', 'question_type' => 'essay', 'points' => 20]);
    $attempt = QuizAttempt::create(['quiz_id' => $quiz->id, 'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'started_at' => now()]);
    $attempt->setRelation('quiz', $quiz)->finalize([$soal->id => 'Jawaban']);
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $kelas->dosen->user_id, 'judul_tugas' => 'Tugas']);
    $pengumpulan = PengumpulanTugas::create(['tugas_id' => $tugas->id, 'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'file_jawaban' => ['pengumpulan-tugas/a.pdf'], 'submitted_at' => now()]);
    $kelas->tahunAkademik->update(['status' => false]);
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.quiz.attempts.grade', [$kelas, $quiz, $attempt]), ['points' => [$soal->id => 10]])->assertForbidden();
    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.tugas.pengumpulan.nilai', [$kelas, $tugas, $pengumpulan]), ['nilai' => 90])->assertForbidden();
    $this->actingAs($dosen)->delete(route('dosen.kelas-kuliah.quiz.questions.destroy', [$kelas, $quiz, $soal]))->assertForbidden();

    expect((float) $attempt->fresh()->score)->toBe(0.0)
        ->and($pengumpulan->fresh()->nilai)->toBeNull()
        ->and($quiz->questions()->count())->toBe(1);

    // Admin tetap bisa mengoreksi.
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.kelas-kuliah.quiz.attempts.grade', [$kelas, $quiz, $attempt]), ['points' => [$soal->id => 10]])
        ->assertSessionHas('success');
});
