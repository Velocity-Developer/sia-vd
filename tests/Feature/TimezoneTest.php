<?php

use App\Models\Krs;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;

it('runs the application in WIB', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(now()->utcOffset())->toBe(7 * 60);
});

it('serializes dates with the WIB offset so the frontend shows the entered wall-clock time', function () {
    $kelas = createMateriKelasKuliah();
    $quiz = $kelas->quizzes()->create(['nama_quiz' => 'Quiz', 'tenggat_waktu' => '2026-09-22T23:59', 'uploaded_by' => $kelas->dosen->user_id]);

    expect($quiz->fresh()->toArray()['tenggat_waktu'])->toBe('2026-09-22T23:59:00+07:00');
});

it('closes a quiz at the entered WIB deadline, not seven hours later', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();
    $kelas = createMateriKelasKuliah();
    Krs::create(['mahasiswa_id' => $mahasiswa->mahasiswaProfile->id, 'kelas_id' => $kelas->id]);
    $quiz = $kelas->quizzes()->create(['nama_quiz' => 'Quiz', 'tenggat_waktu' => '2026-09-22 23:59:00', 'uploaded_by' => $kelas->dosen->user_id]);

    $this->travelTo(Carbon::parse('2026-09-23 00:30:00', 'Asia/Jakarta'));

    $this->actingAs($mahasiswa)
        ->post(route('mahasiswa.quiz.start', $quiz))
        ->assertSessionHas('error', 'Tenggat quiz telah berakhir.');

    expect(QuizAttempt::count())->toBe(0);
});
