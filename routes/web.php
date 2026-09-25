<?php

use App\Http\Controllers\Admin\FakultasController;
use App\Http\Controllers\Admin\InfoKuliahController;
use App\Http\Controllers\Admin\JenisBiayaController;
use App\Http\Controllers\Admin\MataKuliahController;
use App\Http\Controllers\Admin\PindahKelasController as AdminPindahKelasController;
use App\Http\Controllers\Admin\ProgramStudiController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RuangController;
use App\Http\Controllers\Admin\TagihanController;
use App\Http\Controllers\Admin\TagihanRemidiController;
use App\Http\Controllers\Admin\TahunAkademikController;
use App\Http\Controllers\Admin\UjianController as AdminUjianController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\Dosen\MahasiswaKelasController;
use App\Http\Controllers\Dosen\UjianController as DosenUjianController;
use App\Http\Controllers\Kelas\JadwalController;
use App\Http\Controllers\Kelas\KelasKuliahController;
use App\Http\Controllers\Kelas\MateriController;
use App\Http\Controllers\Kelas\PengajuanIzinController;
use App\Http\Controllers\Kelas\PertemuanController;
use App\Http\Controllers\Kelas\PresensiController;
use App\Http\Controllers\Kelas\QuizController;
use App\Http\Controllers\Kelas\QuizPenilaianController;
use App\Http\Controllers\Kelas\RemidiController;
use App\Http\Controllers\Kelas\TugasController;
use App\Http\Controllers\Kelas\UjianKelasController;
use App\Http\Controllers\Mahasiswa\ContentController as MahasiswaContentController;
use App\Http\Controllers\Mahasiswa\HasilStudiController;
use App\Http\Controllers\Mahasiswa\InfoBiayaKuliahController;
use App\Http\Controllers\Mahasiswa\InfoKuliahController as MahasiswaInfoKuliahController;
use App\Http\Controllers\Mahasiswa\KrsController;
use App\Http\Controllers\Mahasiswa\PengumpulanTugasController;
use App\Http\Controllers\Mahasiswa\PindahKelasController as MahasiswaPindahKelasController;
use App\Http\Controllers\Mahasiswa\PresensiController as MahasiswaPresensiController;
use App\Http\Controllers\Mahasiswa\QuizAttemptController;
use App\Http\Controllers\Mahasiswa\TagihanRemidiController as MahasiswaTagihanRemidiController;
use App\Http\Controllers\Mahasiswa\UjianController as MahasiswaUjianController;
use App\Models\KelasKuliah;
use App\Models\Materi;
use App\Models\Quiz;
use App\Models\User;
use App\PengingatRemidi;
use App\PeringatanPresensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Detail ujian (soal, pengumpulan, nilai) sama untuk admin dan dosen pengampu.
$ruteUjianKelas = function (string $peran): void {
    Route::get('ujian/{ujian}', [UjianKelasController::class, 'show'])->name($peran.'.ujian.show');
    Route::post('ujian/{ujian}/soal', [UjianKelasController::class, 'unggahSoal'])->name($peran.'.ujian.soal.unggah');
    Route::delete('ujian/{ujian}/soal/{index}', [UjianKelasController::class, 'hapusSoal'])->whereNumber('index')->name($peran.'.ujian.soal.hapus');
    Route::put('ujian/{ujian}/nilai/{mahasiswa}', [UjianKelasController::class, 'nilai'])->name($peran.'.ujian.nilai');
    Route::put('ujian/{ujian}/rilis-nilai', [UjianKelasController::class, 'rilisNilai'])->name($peran.'.ujian.rilis-nilai');
    Route::post('ujian/{ujian}/lembar-soal', [UjianKelasController::class, 'buatSoal'])->name($peran.'.ujian.lembar-soal');
    Route::get('ujian/{ujian}/daftar-hadir', [UjianKelasController::class, 'daftarHadir'])->name($peran.'.ujian.daftar-hadir');
};

// Rute presensi sama untuk admin dan dosen; bedanya hanya prefix nama dan izin grup.
$rutePresensi = function (string $peran): void {
    Route::get('presensi', [PresensiController::class, 'index'])->name($peran.'.presensi.index');
    Route::get('presensi/kelas/{kelasKuliah}', [PresensiController::class, 'kelas'])->name($peran.'.presensi.kelas');
    Route::post('presensi/kelas/{kelasKuliah}/generate', [PresensiController::class, 'generate'])->name($peran.'.presensi.generate');
    Route::post('presensi/kelas/{kelasKuliah}/susun-ulang', [PresensiController::class, 'susunUlang'])->name($peran.'.presensi.susun-ulang');
    Route::get('presensi/kelas/{kelasKuliah}/ekspor', [PresensiController::class, 'ekspor'])->name($peran.'.presensi.ekspor');
    Route::get('presensi/kelas/{kelasKuliah}/peserta-ujian', [PresensiController::class, 'pesertaUjian'])->name($peran.'.presensi.peserta-ujian');
    Route::post('presensi/kelas/{kelasKuliah}/dispensasi', [PresensiController::class, 'dispensasiSimpan'])->name($peran.'.presensi.dispensasi.simpan');
    Route::delete('presensi/dispensasi/{dispensasi}', [PresensiController::class, 'dispensasiHapus'])->name($peran.'.presensi.dispensasi.hapus');
    Route::get('presensi/izin', [PengajuanIzinController::class, 'index'])->name($peran.'.presensi.izin.index');
    Route::put('presensi/izin/{pengajuanIzin}', [PengajuanIzinController::class, 'proses'])->name($peran.'.presensi.izin.proses');
    Route::get('presensi/pertemuan/{pertemuan}', [PertemuanController::class, 'show'])->name($peran.'.presensi.pertemuan.show');
    Route::put('presensi/pertemuan/{pertemuan}', [PertemuanController::class, 'update'])->name($peran.'.presensi.pertemuan.update');
    Route::put('presensi/pertemuan/{pertemuan}/batal', [PertemuanController::class, 'batal'])->name($peran.'.presensi.pertemuan.batal');
    Route::put('presensi/pertemuan/{pertemuan}/aktifkan', [PertemuanController::class, 'aktifkan'])->name($peran.'.presensi.pertemuan.aktifkan');
    Route::post('presensi/pertemuan/{pertemuan}/mulai', [PertemuanController::class, 'mulai'])->name($peran.'.presensi.pertemuan.mulai');
    Route::post('presensi/pertemuan/{pertemuan}/selesai', [PertemuanController::class, 'selesai'])->name($peran.'.presensi.pertemuan.selesai');
    Route::put('presensi/pertemuan/{pertemuan}/jurnal', [PertemuanController::class, 'jurnal'])->name($peran.'.presensi.pertemuan.jurnal');
    Route::put('presensi/pertemuan/{pertemuan}/mahasiswa', [PertemuanController::class, 'simpanPresensi'])->name($peran.'.presensi.pertemuan.mahasiswa');
    Route::post('presensi/pertemuan/{pertemuan}/mandiri', [PertemuanController::class, 'bukaMandiri'])->name($peran.'.presensi.pertemuan.mandiri.buka');
    Route::delete('presensi/pertemuan/{pertemuan}/mandiri', [PertemuanController::class, 'tutupMandiri'])->name($peran.'.presensi.pertemuan.mandiri.tutup');
    Route::get('presensi/pertemuan/{pertemuan}/kode', [PertemuanController::class, 'kode'])->name($peran.'.presensi.pertemuan.kode');
};

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('admin', fn () => Inertia::render('Dashboard', ['role' => 'admin']))
    ->middleware(['auth', 'verified', 'can:admin.dashboard'])
    ->name('admin.dashboard');

Route::get('admin/data', fn () => Inertia::render('Dashboard', ['role' => 'admin', 'viewAllData' => true]))
    ->middleware(['auth', 'verified', 'can:admin.dashboard'])
    ->name('admin.data');

// Berkas kuliah disimpan di disk privat; hak akses dicek di BerkasController.
Route::prefix('berkas')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('materi/{materi}/{index}', [BerkasController::class, 'materi'])->whereNumber('index')->name('berkas.materi');
    Route::get('tugas/{tugas}/{index}', [BerkasController::class, 'tugas'])->whereNumber('index')->name('berkas.tugas');
    Route::get('pengumpulan/{pengumpulan}/{index}', [BerkasController::class, 'pengumpulan'])->whereNumber('index')->name('berkas.pengumpulan');
    Route::get('info-kuliah/{infoKuliah}', [BerkasController::class, 'infoKuliah'])->name('berkas.info-kuliah');
    Route::get('izin/{pengajuanIzin}/{index}', [BerkasController::class, 'izin'])->whereNumber('index')->name('berkas.izin');
    Route::get('bukti-remidi/{tagihanRemidi}', [BerkasController::class, 'buktiRemidi'])->name('berkas.bukti-remidi');
    Route::get('ujian-soal/{ujian}/{index}', [BerkasController::class, 'soalUjian'])->whereNumber('index')->name('berkas.ujian-soal');
    Route::get('ujian-jawaban/{jawaban}/{index}', [BerkasController::class, 'jawabanUjian'])->whereNumber('index')->name('berkas.ujian-jawaban');
});

Route::prefix('admin/users')->middleware(['auth', 'verified'])->group(function () {
    foreach (['dosen', 'mahasiswa', 'karyawan'] as $type) {
        Route::middleware('can:admin.users.'.$type)->group(function () use ($type): void {
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
        });
    }
});

Route::prefix('admin')->middleware(['auth', 'verified'])->group(function () use ($rutePresensi, $ruteUjianKelas): void {
    Route::middleware('can:admin.roles')->group(function (): void {
        Route::resource('roles', RoleController::class)->except('show')->names('admin.roles');
    });

    Route::middleware('can:admin.info-kuliah')->group(function (): void {
        Route::resource('info-kuliah', InfoKuliahController::class)->except('show')->parameters(['info-kuliah' => 'infoKuliah'])->names('admin.info-kuliah');
    });

    Route::middleware('can:admin.jenis-biaya')->group(function (): void {
        Route::get('jenis-biaya', [JenisBiayaController::class, 'index'])->name('admin.jenis-biaya.index');
        Route::get('jenis-biaya/create', [JenisBiayaController::class, 'create'])->name('admin.jenis-biaya.create');
        Route::post('jenis-biaya', [JenisBiayaController::class, 'store'])->name('admin.jenis-biaya.store');
        Route::get('jenis-biaya/{jenisBiaya}/edit', [JenisBiayaController::class, 'edit'])->name('admin.jenis-biaya.edit');
        Route::put('jenis-biaya/{jenisBiaya}', [JenisBiayaController::class, 'update'])->name('admin.jenis-biaya.update');
        Route::delete('jenis-biaya/{jenisBiaya}', [JenisBiayaController::class, 'destroy'])->name('admin.jenis-biaya.destroy');
    });

    Route::middleware('can:admin.tagihan')->group(function (): void {
        Route::get('tagihan', [TagihanController::class, 'index'])->name('admin.tagihan.index');
        Route::get('tagihan/{mahasiswa}/rincian', [TagihanController::class, 'rincian'])->name('admin.tagihan.rincian');
        Route::post('tagihan/terbitkan', [TagihanController::class, 'terbitkan'])->name('admin.tagihan.terbitkan');
        Route::put('tagihan/status', [TagihanController::class, 'ubahStatus'])->name('admin.tagihan.status');
        Route::put('tagihan/{mahasiswa}/rincian', [TagihanController::class, 'simpanRincian'])->name('admin.tagihan.rincian.simpan');
        Route::delete('tagihan/{mahasiswa}/kunci-krs', [TagihanController::class, 'bukaKunciKrs'])->name('admin.tagihan.buka-kunci-krs');
        Route::get('tagihan-remidi', [TagihanRemidiController::class, 'index'])->name('admin.tagihan-remidi.index');
        Route::post('tagihan-remidi/terbitkan', [TagihanRemidiController::class, 'terbitkan'])->name('admin.tagihan-remidi.terbitkan');
        Route::post('tagihan-remidi/kunci-massal', [TagihanRemidiController::class, 'kunciMassal'])->name('admin.tagihan-remidi.kunci-massal');
        Route::post('tagihan-remidi/{tagihanRemidi}/lunas', [TagihanRemidiController::class, 'lunas'])->name('admin.tagihan-remidi.lunas');
        Route::post('tagihan-remidi/{tagihanRemidi}/tolak', [TagihanRemidiController::class, 'tolak'])->name('admin.tagihan-remidi.tolak');
    });

    Route::middleware('can:admin.ujian')->group(function () use ($ruteUjianKelas): void {
        Route::get('ujian', [AdminUjianController::class, 'index'])->name('admin.ujian.index');
        Route::get('ujian/create', [AdminUjianController::class, 'create'])->name('admin.ujian.create');
        Route::post('ujian', [AdminUjianController::class, 'store'])->name('admin.ujian.store');
        Route::post('ujian/buat-massal', [AdminUjianController::class, 'buatMassal'])->name('admin.ujian.buat-massal');
        Route::post('ujian/remidi-massal', [AdminUjianController::class, 'buatRemidiMassal'])->name('admin.ujian.remidi-massal');
        Route::put('ujian/terbitkan', [AdminUjianController::class, 'terbitkan'])->name('admin.ujian.terbitkan');
        Route::get('ujian/{ujian}/edit', [AdminUjianController::class, 'edit'])->name('admin.ujian.edit');
        Route::put('ujian/{ujian}', [AdminUjianController::class, 'update'])->name('admin.ujian.update');
        Route::delete('ujian/{ujian}', [AdminUjianController::class, 'destroy'])->name('admin.ujian.destroy');
        $ruteUjianKelas('admin');
    });

    Route::middleware('can:admin.pindah-kelas')->group(function (): void {
        Route::get('pindah-kelas', [AdminPindahKelasController::class, 'index'])->name('admin.pindah-kelas.index');
        Route::put('pindah-kelas/{pengajuan}/approve', [AdminPindahKelasController::class, 'approve'])->name('admin.pindah-kelas.approve');
        Route::put('pindah-kelas/{pengajuan}/reject', [AdminPindahKelasController::class, 'reject'])->name('admin.pindah-kelas.reject');
    });

    Route::middleware('can:admin.fakultas')->group(function (): void {
        Route::resource('fakultas', FakultasController::class)->parameters(['fakultas' => 'fakulta'])->names('admin.fakultas');
    });

    Route::middleware('can:admin.program-studi')->group(function (): void {
        Route::resource('program-studi', ProgramStudiController::class)->names('admin.program-studi');
    });

    Route::middleware('can:admin.mata-kuliah')->group(function (): void {
        Route::resource('mata-kuliah', MataKuliahController::class)->parameters(['mata_kuliah' => 'mataKuliah'])->names('admin.mata-kuliah');
    });

    Route::middleware('can:admin.ruang')->group(function (): void {
        Route::resource('ruang', RuangController::class)->parameters(['ruang' => 'ruang'])->names('admin.ruang');
    });

    Route::middleware('can:admin.tahun-akademik')->group(function (): void {
        Route::resource('tahun-akademik', TahunAkademikController::class)->except('show')->parameters(['tahun-akademik' => 'tahunAkademik'])->names('admin.tahun-akademik');
    });

    // Menu tersendiri untuk konten kelas, dengan filter lintas kelas.
    Route::middleware('can:admin.jadwal')->group(fn () => Route::get('jadwal', [JadwalController::class, 'index'])->name('admin.jadwal.index'));
    Route::middleware('can:admin.materi')->group(fn () => Route::get('materi', [MateriController::class, 'index'])->name('admin.materi.index'));
    Route::middleware('can:admin.tugas')->group(fn () => Route::get('tugas', [TugasController::class, 'index'])->name('admin.tugas.index'));
    Route::middleware('can:admin.quiz')->group(fn () => Route::get('quiz', [QuizController::class, 'index'])->name('admin.quiz.index'));
    Route::middleware('can:admin.presensi')->group(function () use ($rutePresensi): void {
        $rutePresensi('admin');
        Route::put('presensi/kelas/{kelasKuliah}/jumlah', [PresensiController::class, 'ubahJumlah'])->name('admin.presensi.jumlah');
        Route::get('presensi/laporan-dosen', [PresensiController::class, 'laporanDosen'])->name('admin.presensi.laporan-dosen');
    });

    Route::middleware('can:admin.kelas-kuliah')->group(function (): void {
        Route::resource('kelas-kuliah', KelasKuliahController::class)->parameters(['kelas_kuliah' => 'kelasKuliah'])->names('admin.kelas-kuliah');
        Route::put('kelas-kuliah/{kelasKuliah}/krs/{krs}/nilai', [KelasKuliahController::class, 'updateGrade'])->name('admin.kelas-kuliah.krs.nilai');
        Route::post('kelas-kuliah/{kelasKuliah}/finalisasi-nilai', [KelasKuliahController::class, 'finalisasiNilai'])->name('admin.kelas-kuliah.finalisasi-nilai');
        Route::post('kelas-kuliah/{kelasKuliah}/buka-kunci-nilai', [KelasKuliahController::class, 'bukaKunciNilai'])->name('admin.kelas-kuliah.buka-kunci-nilai');
        Route::post('kelas-kuliah/{kelasKuliah}/remidi/kunci', [RemidiController::class, 'kunci'])->name('admin.kelas-kuliah.remidi.kunci');
        Route::post('kelas-kuliah/{kelasKuliah}/remidi/buka', [RemidiController::class, 'buka'])->name('admin.kelas-kuliah.remidi.buka');
        Route::post('kelas-kuliah/{kelasKuliah}/remidi/finalisasi', [RemidiController::class, 'finalisasi'])->name('admin.kelas-kuliah.remidi.finalisasi');
        Route::post('kelas-kuliah/{kelasKuliah}/remidi/buka-finalisasi', [RemidiController::class, 'bukaFinalisasi'])->name('admin.kelas-kuliah.remidi.buka-finalisasi');
        Route::delete('kelas-kuliah/{kelasKuliah}/krs/{krs}', [KelasKuliahController::class, 'destroyKrs'])->name('admin.kelas-kuliah.krs.destroy');
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
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/attempts/{attempt}', [QuizPenilaianController::class, 'show'])->name('admin.kelas-kuliah.quiz.attempts.show');
        Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/attempts/{attempt}/nilai', [QuizPenilaianController::class, 'grade'])->name('admin.kelas-kuliah.quiz.attempts.grade');
    });
});

Route::prefix('dosen')->middleware(['auth', 'verified'])->group(function () use ($rutePresensi, $ruteUjianKelas) {
    Route::middleware('can:dosen.dashboard')->group(function (): void {
        Route::get('/', fn (Request $request) => Inertia::render('Dashboard', [
            'presensiDosen' => $request->user()->dosenProfile && $request->user()->hasPermission('dosen.presensi')
                ? PeringatanPresensi::untukDosen($request->user()->dosenProfile)
                : null,
            'remidiDosen' => $request->user()->dosenProfile && $request->user()->hasPermission('dosen.kelas-kuliah')
                ? PengingatRemidi::untukDosen($request->user()->dosenProfile)
                : null,
        ]))->name('dosen.dashboard');
        Route::get('profile', fn () => Inertia::render('DosenPlaceholder', ['title' => 'Profile']))->name('dosen.profile');
    });

    Route::middleware('can:dosen.jadwal')->group(fn () => Route::get('jadwal', [JadwalController::class, 'index'])->name('dosen.jadwal.index'));
    Route::middleware('can:dosen.materi')->group(fn () => Route::get('materi', [MateriController::class, 'index'])->name('dosen.materi.index'));
    Route::middleware('can:dosen.tugas')->group(fn () => Route::get('tugas', [TugasController::class, 'index'])->name('dosen.tugas.index'));
    Route::middleware('can:dosen.quiz')->group(fn () => Route::get('quiz', [QuizController::class, 'index'])->name('dosen.quiz.index'));
    Route::middleware('can:dosen.presensi')->group(fn () => $rutePresensi('dosen'));

    Route::middleware('can:dosen.kelas-kuliah')->group(function (): void {
        Route::get('kelas-kuliah', [KelasKuliahController::class, 'index'])->name('dosen.kelas-kuliah.index');
        Route::get('kelas-kuliah/{kelasKuliah}', [KelasKuliahController::class, 'show'])->name('dosen.kelas-kuliah.show');
        Route::put('kelas-kuliah/{kelasKuliah}/krs/{krs}/nilai', [KelasKuliahController::class, 'updateGrade'])->name('dosen.kelas-kuliah.krs.nilai');
        Route::post('kelas-kuliah/{kelasKuliah}/finalisasi-nilai', [KelasKuliahController::class, 'finalisasiNilai'])->name('dosen.kelas-kuliah.finalisasi-nilai');
        Route::post('kelas-kuliah/{kelasKuliah}/remidi/kunci', [RemidiController::class, 'kunci'])->name('dosen.kelas-kuliah.remidi.kunci');
        Route::post('kelas-kuliah/{kelasKuliah}/remidi/finalisasi', [RemidiController::class, 'finalisasi'])->name('dosen.kelas-kuliah.remidi.finalisasi');
        Route::get('kelas-kuliah/{kelasKuliah}/materi/create', [MateriController::class, 'create'])->name('dosen.kelas-kuliah.materi.create');
        Route::post('kelas-kuliah/{kelasKuliah}/materi', [MateriController::class, 'store'])->name('dosen.kelas-kuliah.materi.store');
        Route::get('kelas-kuliah/{kelasKuliah}/materi/{materi}/edit', [MateriController::class, 'edit'])->name('dosen.kelas-kuliah.materi.edit');
        Route::put('kelas-kuliah/{kelasKuliah}/materi/{materi}', [MateriController::class, 'update'])->name('dosen.kelas-kuliah.materi.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/materi/{materi}', [MateriController::class, 'destroy'])->name('dosen.kelas-kuliah.materi.destroy');
        Route::post('kelas-kuliah/{kelasKuliah}/materi/{materi}/duplicate', [MateriController::class, 'duplicate'])->name('dosen.kelas-kuliah.materi.duplicate');
        Route::get('kelas-kuliah/{kelasKuliah}/tugas/create', [TugasController::class, 'create'])->name('dosen.kelas-kuliah.tugas.create');
        Route::post('kelas-kuliah/{kelasKuliah}/tugas', [TugasController::class, 'store'])->name('dosen.kelas-kuliah.tugas.store');
        Route::get('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'show'])->name('dosen.kelas-kuliah.tugas.show');
        Route::put('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/pengumpulan/{pengumpulanTugas}/nilai', [TugasController::class, 'updateSubmissionGrade'])->name('dosen.kelas-kuliah.tugas.pengumpulan.nilai');
        Route::get('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/edit', [TugasController::class, 'edit'])->name('dosen.kelas-kuliah.tugas.edit');
        Route::put('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'update'])->name('dosen.kelas-kuliah.tugas.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'destroy'])->name('dosen.kelas-kuliah.tugas.destroy');
        Route::post('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/duplicate', [TugasController::class, 'duplicate'])->name('dosen.kelas-kuliah.tugas.duplicate');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/create', [QuizController::class, 'create'])->name('dosen.kelas-kuliah.quiz.create');
        Route::post('kelas-kuliah/{kelasKuliah}/quiz', [QuizController::class, 'store'])->name('dosen.kelas-kuliah.quiz.store');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'show'])->name('dosen.kelas-kuliah.quiz.show');
        Route::post('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions', [QuizController::class, 'storeQuestions'])->name('dosen.kelas-kuliah.quiz.questions.store');
        Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions/{question}', [QuizController::class, 'updateQuestion'])->name('dosen.kelas-kuliah.quiz.questions.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions/{question}', [QuizController::class, 'destroyQuestion'])->name('dosen.kelas-kuliah.quiz.questions.destroy');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/edit', [QuizController::class, 'edit'])->name('dosen.kelas-kuliah.quiz.edit');
        Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'update'])->name('dosen.kelas-kuliah.quiz.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'destroy'])->name('dosen.kelas-kuliah.quiz.destroy');
        Route::post('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/duplicate', [QuizController::class, 'duplicate'])->name('dosen.kelas-kuliah.quiz.duplicate');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/attempts/{attempt}', [QuizPenilaianController::class, 'show'])->name('dosen.kelas-kuliah.quiz.attempts.show');
        Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/attempts/{attempt}/nilai', [QuizPenilaianController::class, 'grade'])->name('dosen.kelas-kuliah.quiz.attempts.grade');
        Route::redirect('jadwal-kuliah', '/dosen/kelas-kuliah', 301)->name('dosen.jadwal-kuliah');
    });

    Route::middleware('can:dosen.ujian')->group(function () use ($ruteUjianKelas): void {
        Route::get('ujian', [DosenUjianController::class, 'index'])->name('dosen.ujian.index');
        $ruteUjianKelas('dosen');
    });

    Route::middleware('can:dosen.mahasiswa-kelas')->group(function (): void {
        Route::get('mahasiswa-kelas', [MahasiswaKelasController::class, 'index'])->name('dosen.mahasiswa-kelas');
    });
});

Route::prefix('mahasiswa')->middleware(['auth', 'verified'])->group(function () {
    Route::middleware('can:mahasiswa.dashboard')->group(function (): void {
        Route::get('/', fn (Request $request) => Inertia::render('Dashboard', [
            'peringatanPresensi' => $request->user()->mahasiswaProfile && $request->user()->hasPermission('mahasiswa.presensi')
                ? PeringatanPresensi::untukMahasiswa($request->user()->mahasiswaProfile)
                : null,
            'remidiMahasiswa' => $request->user()->mahasiswaProfile
                ? PengingatRemidi::untukMahasiswa($request->user()->mahasiswaProfile, $request->user()->hasPermission('mahasiswa.info-biaya'), $request->user()->hasPermission('mahasiswa.ujian'))
                : null,
        ]))->name('mahasiswa.dashboard');

        foreach ([
            'profile' => 'Profile', 'info-perkuliahan' => 'Info Perkuliahan',
            'pendaftaran-wisuda' => 'Pendaftaran Wisuda',
        ] as $path => $title) {
            Route::get($path, fn () => Inertia::render('MahasiswaPlaceholder', ['title' => $title]))
                ->name('mahasiswa.'.$path);
        }
    });

    Route::middleware('can:mahasiswa.info-biaya')->group(function (): void {
        Route::get('info-biaya-kuliah', [InfoBiayaKuliahController::class, 'index'])->name('mahasiswa.info-biaya-kuliah');
        Route::post('tagihan-remidi/{tagihanRemidi}/bukti', [MahasiswaTagihanRemidiController::class, 'unggahBukti'])->name('mahasiswa.tagihan-remidi.bukti');
    });

    Route::middleware('can:mahasiswa.info-kuliah')->group(function (): void {
        Route::get('info-kuliah', [MahasiswaInfoKuliahController::class, 'index'])->name('mahasiswa.info-kuliah');
    });

    Route::middleware(['can:mahasiswa.krs', 'tagihan.lunas'])->group(function (): void {
        Route::get('krs', [KrsController::class, 'index'])->name('mahasiswa.krs');
        Route::post('krs/simpan', [KrsController::class, 'simpan'])->name('mahasiswa.krs.simpan');
        Route::post('krs/{kelasKuliah}', [KrsController::class, 'store'])->name('mahasiswa.krs.store');
        Route::delete('krs/{krs}', [KrsController::class, 'destroy'])->name('mahasiswa.krs.destroy');
    });

    Route::middleware('can:mahasiswa.hasil-studi')->group(function (): void {
        Route::get('hasil-studi', [HasilStudiController::class, 'index'])->name('mahasiswa.hasil-studi');
        Route::get('hasil-studi/download', [HasilStudiController::class, 'downloadKhs'])->name('mahasiswa.hasil-studi.download');
        Route::get('transkrip', [HasilStudiController::class, 'transkrip'])->name('mahasiswa.transkrip');
        Route::get('khs/transkrip-nilai', [HasilStudiController::class, 'transkrip'])->name('mahasiswa.khs.transkrip-nilai');
        Route::get('khs', [HasilStudiController::class, 'index'])->name('mahasiswa.khs');
    });

    Route::middleware('can:mahasiswa.presensi')->group(function (): void {
        Route::get('presensi', [MahasiswaPresensiController::class, 'index'])->name('mahasiswa.presensi');
        Route::get('presensi/masuk/{pertemuan}', [MahasiswaPresensiController::class, 'masuk'])->name('mahasiswa.presensi.masuk');
        Route::post('presensi', [MahasiswaPresensiController::class, 'checkIn'])->middleware('throttle:10,1')->name('mahasiswa.presensi.check-in');
        Route::post('presensi/izin', [MahasiswaPresensiController::class, 'ajukanIzin'])->name('mahasiswa.presensi.izin');
    });

    Route::middleware('can:mahasiswa.ujian')->group(function (): void {
        Route::get('ujian', [MahasiswaUjianController::class, 'index'])->name('mahasiswa.ujian');
        Route::get('ujian/kartu', [MahasiswaUjianController::class, 'kartu'])->name('mahasiswa.ujian.kartu');
        Route::get('ujian/{ujian}', [MahasiswaUjianController::class, 'show'])->name('mahasiswa.ujian.show');
        Route::post('ujian/{ujian}/jawaban', [MahasiswaUjianController::class, 'kumpulkan'])->middleware('throttle:20,1')->name('mahasiswa.ujian.kumpulkan');
    });

    Route::middleware('can:mahasiswa.pindah-kelas')->group(function (): void {
        Route::get('pindah-kelas', [MahasiswaPindahKelasController::class, 'index'])->name('mahasiswa.pindah-kelas');
        Route::post('pindah-kelas', [MahasiswaPindahKelasController::class, 'store'])->name('mahasiswa.pindah-kelas.store');
    });

    Route::middleware('can:mahasiswa.jadwal-kuliah')->group(function (): void {
        Route::get('tugas/{tugas}', [PengumpulanTugasController::class, 'show'])->name('mahasiswa.tugas.show');
        Route::post('tugas/{tugas}/pengumpulan', [PengumpulanTugasController::class, 'store'])->name('mahasiswa.tugas.pengumpulan.store');
        Route::get('materi/{materi}', fn (Request $request, Materi $materi) => app(MahasiswaContentController::class)->materiShow($request, $materi))->name('mahasiswa.materi.show');
        Route::get('quiz/{quiz}', fn (Request $request, Quiz $quiz) => app(MahasiswaContentController::class)->quizShow($request, $quiz))->name('mahasiswa.quiz.show');
        Route::post('quiz/{quiz}/start', [QuizAttemptController::class, 'start'])->name('mahasiswa.quiz.start');
        Route::post('quiz/{quiz}/answers', [QuizAttemptController::class, 'saveAnswers'])->middleware('throttle:60,1')->name('mahasiswa.quiz.answers');
        Route::post('quiz/{quiz}/submit', [QuizAttemptController::class, 'submit'])->name('mahasiswa.quiz.submit');
        Route::get('jadwal', fn (Request $request) => app(MahasiswaContentController::class)->jadwalKuliah($request))
            ->name('mahasiswa.jadwal-kuliah');
        Route::get('jadwal/{kelasKuliah}', fn (Request $request, KelasKuliah $kelasKuliah) => app(MahasiswaContentController::class)->show($request, $kelasKuliah))
            ->name('mahasiswa.jadwal-kuliah.show');
    });

    Route::middleware('can:mahasiswa.perpustakaan')->group(function (): void {
        foreach (['perpustakaan' => 'Perpustakaan', 'perpustakaan/pinjaman-aktif' => 'Pinjaman Aktif', 'perpustakaan/riwayat-pinjaman' => 'Riwayat Pinjaman'] as $path => $title) {
            Route::get($path, fn () => Inertia::render('MahasiswaPlaceholder', ['title' => $title]))
                ->name('mahasiswa.'.str_replace('/', '.', $path));
        }
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
