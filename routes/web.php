<?php

use App\Http\Controllers\Admin\FakultasController;
use App\Http\Controllers\Admin\InfoKuliahController;
use App\Http\Controllers\Admin\JadwalController;
use App\Http\Controllers\Admin\KelasKuliahController;
use App\Http\Controllers\Admin\MataKuliahController;
use App\Http\Controllers\Admin\MateriController;
use App\Http\Controllers\Admin\PindahKelasController as AdminPindahKelasController;
use App\Http\Controllers\Admin\ProgramStudiController;
use App\Http\Controllers\Admin\QuizController;
use App\Http\Controllers\Admin\RuangController;
use App\Http\Controllers\Admin\TahunAkademikController;
use App\Http\Controllers\Admin\TugasController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Dosen\KelasKuliahController as DosenKelasKuliahController;
use App\Http\Controllers\Dosen\MahasiswaKelasController;
use App\Http\Controllers\Dosen\MateriController as DosenMateriController;
use App\Http\Controllers\Dosen\QuizController as DosenQuizController;
use App\Http\Controllers\Dosen\TugasController as DosenTugasController;
use App\Http\Controllers\Mahasiswa\ContentController as MahasiswaContentController;
use App\Http\Controllers\Mahasiswa\HasilStudiController;
use App\Http\Controllers\Mahasiswa\InfoKuliahController as MahasiswaInfoKuliahController;
use App\Http\Controllers\Mahasiswa\KrsController;
use App\Http\Controllers\Mahasiswa\PengumpulanTugasController;
use App\Http\Controllers\Mahasiswa\PindahKelasController as MahasiswaPindahKelasController;
use App\Http\Controllers\Mahasiswa\QuizAttemptController;
use App\Models\KelasKuliah;
use App\Models\Materi;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('admin', fn () => Inertia::render('Dashboard', ['role' => 'admin']))
    ->middleware(['auth', 'verified', 'role:admin'])
    ->name('admin.dashboard');

Route::get('admin/data', fn () => Inertia::render('Dashboard', ['role' => 'admin', 'viewAllData' => true]))
    ->middleware(['auth', 'verified', 'role:admin'])
    ->name('admin.data');

Route::prefix('admin/users')->middleware(['auth', 'verified', 'role:admin'])->group(function () {
    foreach (['dosen', 'mahasiswa', 'karyawan'] as $type) {
        Route::get($type, fn (Request $request) => app(UserController::class)->index($request, $type))
            ->name('admin.users.'.$type);
        Route::get($type.'/create', fn () => app(UserController::class)->create($type))
            ->name('admin.users.'.$type.'.create');
        Route::post($type, fn (Request $request) => app(UserController::class)->store($request, $type))
            ->name('admin.users.'.$type.'.store');
        Route::get($type.'/{user}', fn (User $user) => app(UserController::class)->show($type, $user))
            ->name('admin.users.'.$type.'.show');
        Route::get($type.'/{user}/edit', fn (User $user) => app(UserController::class)->edit($type, $user))
            ->name('admin.users.'.$type.'.edit');
        Route::put($type.'/{user}', fn (Request $request, User $user) => app(UserController::class)->update($request, $type, $user))
            ->name('admin.users.'.$type.'.update');
        Route::delete($type.'/{user}', fn (User $user) => app(UserController::class)->destroy($type, $user))
            ->name('admin.users.'.$type.'.destroy');
    }
});

Route::prefix('admin')->middleware(['auth', 'verified', 'role:admin'])->group(function (): void {
    Route::resource('info-kuliah', InfoKuliahController::class)->parameters(['info-kuliah' => 'infoKuliah'])->names('admin.info-kuliah');
    Route::get('pindah-kelas', [AdminPindahKelasController::class, 'index'])->name('admin.pindah-kelas.index');
    Route::put('pindah-kelas/pengaturan', [AdminPindahKelasController::class, 'updateSetting'])->name('admin.pindah-kelas.pengaturan');
    Route::put('pindah-kelas/{pengajuan}/approve', [AdminPindahKelasController::class, 'approve'])->name('admin.pindah-kelas.approve');
    Route::put('pindah-kelas/{pengajuan}/reject', [AdminPindahKelasController::class, 'reject'])->name('admin.pindah-kelas.reject');
    Route::resource('fakultas', FakultasController::class)->parameters(['fakultas' => 'fakulta'])->names('admin.fakultas');
    Route::resource('program-studi', ProgramStudiController::class)->names('admin.program-studi');
    Route::resource('mata-kuliah', MataKuliahController::class)->parameters(['mata_kuliah' => 'mataKuliah'])->names('admin.mata-kuliah');
    Route::resource('ruang', RuangController::class)->parameters(['ruang' => 'ruang'])->names('admin.ruang');
    Route::resource('tahun-akademik', TahunAkademikController::class)->parameters(['tahun-akademik' => 'tahunAkademik'])->names('admin.tahun-akademik');
    Route::resource('kelas-kuliah', KelasKuliahController::class)->parameters(['kelas_kuliah' => 'kelasKuliah'])->names('admin.kelas-kuliah');
    Route::put('kelas-kuliah/{kelasKuliah}/krs/{krs}/nilai', [KelasKuliahController::class, 'updateGrade'])->name('admin.kelas-kuliah.krs.nilai');
    Route::get('kelas-kuliah/{kelasKuliah}/jadwal/create', [JadwalController::class, 'create'])->name('admin.kelas-kuliah.jadwal.create');
    Route::post('kelas-kuliah/{kelasKuliah}/jadwal', [JadwalController::class, 'store'])->name('admin.kelas-kuliah.jadwal.store');
    Route::get('kelas-kuliah/{kelasKuliah}/jadwal/{jadwal}/edit', [JadwalController::class, 'edit'])->name('admin.kelas-kuliah.jadwal.edit');
    Route::put('kelas-kuliah/{kelasKuliah}/jadwal/{jadwal}', [JadwalController::class, 'update'])->name('admin.kelas-kuliah.jadwal.update');
    Route::delete('kelas-kuliah/{kelasKuliah}/jadwal/{jadwal}', [JadwalController::class, 'destroy'])->name('admin.kelas-kuliah.jadwal.destroy');
    Route::get('kelas-kuliah/{kelasKuliah}/materi/create', [MateriController::class, 'create'])->name('admin.kelas-kuliah.materi.create');
    Route::post('kelas-kuliah/{kelasKuliah}/materi', [MateriController::class, 'store'])->name('admin.kelas-kuliah.materi.store');
    Route::get('kelas-kuliah/{kelasKuliah}/materi/{materi}/edit', [MateriController::class, 'edit'])->name('admin.kelas-kuliah.materi.edit');
    Route::put('kelas-kuliah/{kelasKuliah}/materi/{materi}', [MateriController::class, 'update'])->name('admin.kelas-kuliah.materi.update');
    Route::delete('kelas-kuliah/{kelasKuliah}/materi/{materi}', [MateriController::class, 'destroy'])->name('admin.kelas-kuliah.materi.destroy');
    Route::post('kelas-kuliah/{kelasKuliah}/materi/{materi}/duplicate', [MateriController::class, 'duplicate'])->name('admin.kelas-kuliah.materi.duplicate');
    Route::get('kelas-kuliah/{kelasKuliah}/tugas/create', [TugasController::class, 'create'])->name('admin.kelas-kuliah.tugas.create');
    Route::post('kelas-kuliah/{kelasKuliah}/tugas', [TugasController::class, 'store'])->name('admin.kelas-kuliah.tugas.store');
    Route::get('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'show'])->name('admin.kelas-kuliah.tugas.show');
    Route::put('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/pengumpulan/{pengumpulanTugas}/nilai', [TugasController::class, 'updateSubmissionGrade'])->name('admin.kelas-kuliah.tugas.pengumpulan.nilai');
    Route::get('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/edit', [TugasController::class, 'edit'])->name('admin.kelas-kuliah.tugas.edit');
    Route::put('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'update'])->name('admin.kelas-kuliah.tugas.update');
    Route::delete('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'destroy'])->name('admin.kelas-kuliah.tugas.destroy');
    Route::post('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/duplicate', [TugasController::class, 'duplicate'])->name('admin.kelas-kuliah.tugas.duplicate');
    Route::get('kelas-kuliah/{kelasKuliah}/quiz/create', [QuizController::class, 'create'])->name('admin.kelas-kuliah.quiz.create');
    Route::post('kelas-kuliah/{kelasKuliah}/quiz', [QuizController::class, 'store'])->name('admin.kelas-kuliah.quiz.store');
    Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'show'])->name('admin.kelas-kuliah.quiz.show');
    Route::post('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions', [QuizController::class, 'storeQuestions'])->name('admin.kelas-kuliah.quiz.questions.store');
    Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions/{question}', [QuizController::class, 'updateQuestion'])->name('admin.kelas-kuliah.quiz.questions.update');
    Route::delete('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions/{question}', [QuizController::class, 'destroyQuestion'])->name('admin.kelas-kuliah.quiz.questions.destroy');
    Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/edit', [QuizController::class, 'edit'])->name('admin.kelas-kuliah.quiz.edit');
    Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'update'])->name('admin.kelas-kuliah.quiz.update');
    Route::delete('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'destroy'])->name('admin.kelas-kuliah.quiz.destroy');
    Route::post('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/duplicate', [QuizController::class, 'duplicate'])->name('admin.kelas-kuliah.quiz.duplicate');
});

Route::prefix('dosen')->middleware(['auth', 'verified', 'role:dosen'])->group(function () {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dosen.dashboard');
    Route::get('profile', fn () => Inertia::render('DosenPlaceholder', ['title' => 'Profile']))->name('dosen.profile');
    Route::get('kelas-kuliah', [DosenKelasKuliahController::class, 'index'])->name('dosen.kelas-kuliah.index');
    Route::get('mahasiswa-kelas', [MahasiswaKelasController::class, 'index'])->name('dosen.mahasiswa-kelas');
    Route::get('kelas-kuliah/{kelasKuliah}', [DosenKelasKuliahController::class, 'show'])->name('dosen.kelas-kuliah.show');
    Route::put('kelas-kuliah/{kelasKuliah}/krs/{krs}/nilai', [DosenKelasKuliahController::class, 'updateGrade'])->name('dosen.kelas-kuliah.krs.nilai');
    Route::get('kelas-kuliah/{kelasKuliah}/materi/create', [DosenMateriController::class, 'create'])->name('dosen.kelas-kuliah.materi.create');
    Route::post('kelas-kuliah/{kelasKuliah}/materi', [DosenMateriController::class, 'store'])->name('dosen.kelas-kuliah.materi.store');
    Route::get('kelas-kuliah/{kelasKuliah}/materi/{materi}/edit', [DosenMateriController::class, 'edit'])->name('dosen.kelas-kuliah.materi.edit');
    Route::put('kelas-kuliah/{kelasKuliah}/materi/{materi}', [DosenMateriController::class, 'update'])->name('dosen.kelas-kuliah.materi.update');
    Route::delete('kelas-kuliah/{kelasKuliah}/materi/{materi}', [DosenMateriController::class, 'destroy'])->name('dosen.kelas-kuliah.materi.destroy');
    Route::post('kelas-kuliah/{kelasKuliah}/materi/{materi}/duplicate', [DosenMateriController::class, 'duplicate'])->name('dosen.kelas-kuliah.materi.duplicate');
    Route::get('kelas-kuliah/{kelasKuliah}/tugas/create', [DosenTugasController::class, 'create'])->name('dosen.kelas-kuliah.tugas.create');
    Route::post('kelas-kuliah/{kelasKuliah}/tugas', [DosenTugasController::class, 'store'])->name('dosen.kelas-kuliah.tugas.store');
    Route::get('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [DosenTugasController::class, 'show'])->name('dosen.kelas-kuliah.tugas.show');
    Route::put('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/pengumpulan/{pengumpulanTugas}/nilai', [DosenTugasController::class, 'updateSubmissionGrade'])->name('dosen.kelas-kuliah.tugas.pengumpulan.nilai');
    Route::get('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/edit', [DosenTugasController::class, 'edit'])->name('dosen.kelas-kuliah.tugas.edit');
    Route::put('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [DosenTugasController::class, 'update'])->name('dosen.kelas-kuliah.tugas.update');
    Route::delete('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [DosenTugasController::class, 'destroy'])->name('dosen.kelas-kuliah.tugas.destroy');
    Route::post('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/duplicate', [DosenTugasController::class, 'duplicate'])->name('dosen.kelas-kuliah.tugas.duplicate');
    Route::get('kelas-kuliah/{kelasKuliah}/quiz/create', [DosenQuizController::class, 'create'])->name('dosen.kelas-kuliah.quiz.create');
    Route::post('kelas-kuliah/{kelasKuliah}/quiz', [DosenQuizController::class, 'store'])->name('dosen.kelas-kuliah.quiz.store');
    Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [DosenQuizController::class, 'show'])->name('dosen.kelas-kuliah.quiz.show');
    Route::post('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions', [DosenQuizController::class, 'storeQuestions'])->name('dosen.kelas-kuliah.quiz.questions.store');
    Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions/{question}', [DosenQuizController::class, 'updateQuestion'])->name('dosen.kelas-kuliah.quiz.questions.update');
    Route::delete('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions/{question}', [DosenQuizController::class, 'destroyQuestion'])->name('dosen.kelas-kuliah.quiz.questions.destroy');
    Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/edit', [DosenQuizController::class, 'edit'])->name('dosen.kelas-kuliah.quiz.edit');
    Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [DosenQuizController::class, 'update'])->name('dosen.kelas-kuliah.quiz.update');
    Route::delete('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [DosenQuizController::class, 'destroy'])->name('dosen.kelas-kuliah.quiz.destroy');
    Route::post('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/duplicate', [DosenQuizController::class, 'duplicate'])->name('dosen.kelas-kuliah.quiz.duplicate');
    Route::redirect('jadwal-kuliah', '/dosen/kelas-kuliah', 301)->name('dosen.jadwal-kuliah');
});

Route::prefix('mahasiswa')->middleware(['auth', 'verified', 'role:mahasiswa'])->group(function () {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('mahasiswa.dashboard');
    Route::get('krs', [KrsController::class, 'index'])->name('mahasiswa.krs');
    Route::get('info-kuliah', [MahasiswaInfoKuliahController::class, 'index'])->name('mahasiswa.info-kuliah');
    Route::get('hasil-studi', [HasilStudiController::class, 'index'])->name('mahasiswa.hasil-studi');
    Route::get('hasil-studi/download', [HasilStudiController::class, 'downloadKhs'])->name('mahasiswa.hasil-studi.download');
    Route::get('transkrip', [HasilStudiController::class, 'transkrip'])->name('mahasiswa.transkrip');
    Route::get('khs/transkrip-nilai', [HasilStudiController::class, 'transkrip'])->name('mahasiswa.khs.transkrip-nilai');
    Route::get('khs', [HasilStudiController::class, 'index'])->name('mahasiswa.khs');
    Route::get('pindah-kelas', [MahasiswaPindahKelasController::class, 'index'])->name('mahasiswa.pindah-kelas');
    Route::post('pindah-kelas', [MahasiswaPindahKelasController::class, 'store'])->name('mahasiswa.pindah-kelas.store');
    Route::post('krs/{kelasKuliah}', [KrsController::class, 'store'])->name('mahasiswa.krs.store');
    Route::get('tugas/{tugas}', [PengumpulanTugasController::class, 'show'])->name('mahasiswa.tugas.show');
    Route::post('tugas/{tugas}/pengumpulan', [PengumpulanTugasController::class, 'store'])->name('mahasiswa.tugas.pengumpulan.store');
    Route::get('materi/{materi}', fn (Request $request, Materi $materi) => app(MahasiswaContentController::class)->materiShow($request, $materi))->name('mahasiswa.materi.show');
    Route::get('quiz/{quiz}', fn (Request $request, Quiz $quiz) => app(MahasiswaContentController::class)->quizShow($request, $quiz))->name('mahasiswa.quiz.show');
    Route::post('quiz/{quiz}/start', [QuizAttemptController::class, 'start'])->name('mahasiswa.quiz.start');
    Route::post('quiz/{quiz}/submit', [QuizAttemptController::class, 'submit'])->name('mahasiswa.quiz.submit');

    Route::get('jadwal', fn (Request $request) => app(MahasiswaContentController::class)->jadwalKuliah($request))
        ->name('mahasiswa.jadwal-kuliah');
    Route::get('jadwal/{kelasKuliah}', fn (Request $request, KelasKuliah $kelasKuliah) => app(MahasiswaContentController::class)->show($request, $kelasKuliah))
        ->name('mahasiswa.jadwal-kuliah.show');

    foreach ([
        'profile' => 'Profile', 'info-perkuliahan' => 'Info Perkuliahan',
        'pendaftaran-wisuda' => 'Pendaftaran Wisuda', 'perpustakaan' => 'Perpustakaan',
        'info-biaya-kuliah' => 'Info Biaya Kuliah',
        'perpustakaan/pinjaman-aktif' => 'Pinjaman Aktif', 'perpustakaan/riwayat-pinjaman' => 'Riwayat Pinjaman',
    ] as $path => $title) {
        Route::get($path, fn () => Inertia::render('MahasiswaPlaceholder', ['title' => $title]))
            ->name('mahasiswa.'.str_replace('/', '.', $path));
    }
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
