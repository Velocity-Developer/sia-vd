<?php

use App\Http\Controllers\Admin\AgamaController;
use App\Http\Controllers\Admin\BadanHukumController;
use App\Http\Controllers\Admin\BatasSksController;
use App\Http\Controllers\Admin\BobotNilaiController;
use App\Http\Controllers\Admin\CetakKrsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DetailNilaiController;
use App\Http\Controllers\Admin\FakultasController;
use App\Http\Controllers\Admin\GelombangKompreController;
use App\Http\Controllers\Admin\ImporDataController;
use App\Http\Controllers\Admin\InfoKuliahController;
use App\Http\Controllers\Admin\InformasiPmbController;
use App\Http\Controllers\Admin\InputKrsController;
use App\Http\Controllers\Admin\JenisBiayaController;
use App\Http\Controllers\Admin\KetuaKelasController;
use App\Http\Controllers\Admin\KhsController;
use App\Http\Controllers\Admin\KomponenNilaiController;
use App\Http\Controllers\Admin\KotaController;
use App\Http\Controllers\Admin\KurikulumController;
use App\Http\Controllers\Admin\LogAktivitasController;
use App\Http\Controllers\Admin\MahasiswaCutiController as AdminMahasiswaCutiController;
use App\Http\Controllers\Admin\MataKuliahController;
use App\Http\Controllers\Admin\NilaiKkmController;
use App\Http\Controllers\Admin\NilaiSemesterController;
use App\Http\Controllers\Admin\PenasehatAkademikController;
use App\Http\Controllers\Admin\PendaftarPmbController;
use App\Http\Controllers\Admin\PendataanNilaiController;
use App\Http\Controllers\Admin\PengajuanAkademikController as AdminPengajuanAkademikController;
use App\Http\Controllers\Admin\PengajuanCutiController as AdminPengajuanCutiController;
use App\Http\Controllers\Admin\PengaturanPmbController;
use App\Http\Controllers\Admin\PenggunaController;
use App\Http\Controllers\Admin\PerguruanTinggiController;
use App\Http\Controllers\Admin\PeriodeWisudaController;
use App\Http\Controllers\Admin\PindahKelasController as AdminPindahKelasController;
use App\Http\Controllers\Admin\PrasyaratController;
use App\Http\Controllers\Admin\PredikatController;
use App\Http\Controllers\Admin\PresensiDosenController;
use App\Http\Controllers\Admin\ProgramStudiController;
use App\Http\Controllers\Admin\ProvinsiController;
use App\Http\Controllers\Admin\RekapKrsController;
use App\Http\Controllers\Admin\RekapPresensiMahasiswaController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RuangController;
use App\Http\Controllers\Admin\StatusKrsController;
use App\Http\Controllers\Admin\SyaratUjianController;
use App\Http\Controllers\Admin\TagihanController;
use App\Http\Controllers\Admin\TagihanRemidiController;
use App\Http\Controllers\Admin\TagihanSusulanController;
use App\Http\Controllers\Admin\TahunAkademikController;
use App\Http\Controllers\Admin\TranskripNilaiController;
use App\Http\Controllers\Admin\UjianController as AdminUjianController;
use App\Http\Controllers\Admin\UjianSusulanController as AdminUjianSusulanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ValidasiNilaiController;
use App\Http\Controllers\Admin\VerifikasiKrsController;
use App\Http\Controllers\Admin\VerifikasiPresensiDosenController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\Dosen\BimbinganController;
use App\Http\Controllers\Dosen\DashboardController as DosenDashboardController;
use App\Http\Controllers\Dosen\MahasiswaKelasController;
use App\Http\Controllers\Dosen\PendadaranController as DosenPendadaranController;
use App\Http\Controllers\Dosen\PengajuanMahasiswaPaController as DosenPengajuanMahasiswaPaController;
use App\Http\Controllers\Dosen\UjianController as DosenUjianController;
use App\Http\Controllers\Kelas\BapController;
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
use App\Http\Controllers\Mahasiswa\CetakKartuController;
use App\Http\Controllers\Mahasiswa\ContentController as MahasiswaContentController;
use App\Http\Controllers\Mahasiswa\DashboardController as MahasiswaDashboardController;
use App\Http\Controllers\Mahasiswa\HasilStudiController;
use App\Http\Controllers\Mahasiswa\InfoBiayaKuliahController;
use App\Http\Controllers\Mahasiswa\InfoKuliahController as MahasiswaInfoKuliahController;
use App\Http\Controllers\Mahasiswa\KrsController;
use App\Http\Controllers\Mahasiswa\PengajuanCutiController as MahasiswaPengajuanCutiController;
use App\Http\Controllers\Mahasiswa\PengajuanKegiatanController as MahasiswaPengajuanKegiatanController;
use App\Http\Controllers\Mahasiswa\PengumpulanTugasController;
use App\Http\Controllers\Mahasiswa\PindahKelasController as MahasiswaPindahKelasController;
use App\Http\Controllers\Mahasiswa\PresensiController as MahasiswaPresensiController;
use App\Http\Controllers\Mahasiswa\QuizAttemptController;
use App\Http\Controllers\Mahasiswa\TagihanRemidiController as MahasiswaTagihanRemidiController;
use App\Http\Controllers\Mahasiswa\TagihanSemesterController as MahasiswaTagihanSemesterController;
use App\Http\Controllers\Mahasiswa\TagihanSusulanController as MahasiswaTagihanSusulanController;
use App\Http\Controllers\Mahasiswa\TugasAkhirController as MahasiswaTugasAkhirController;
use App\Http\Controllers\Mahasiswa\UjianController as MahasiswaUjianController;
use App\Http\Controllers\Mahasiswa\UjianSusulanController as MahasiswaUjianSusulanController;
use App\Http\Controllers\PendadaranBerkasController;
use App\Http\Controllers\SeoController;
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

// Untuk mesin pencari: hanya halaman publik (PMB, login). public/robots.txt statis dihapus agar Sitemap: ikut APP_URL.
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');

// Detail ujian (soal, pengumpulan, nilai) sama untuk admin dan dosen pengampu.
$ruteUjianKelas = function (string $peran): void {
    Route::get('ujian/{ujian}', [UjianKelasController::class, 'show'])->name($peran.'.ujian.show');
    Route::middleware('fitur:ujian_online')->group(function () use ($peran): void {
        Route::post('ujian/{ujian}/soal', [UjianKelasController::class, 'unggahSoal'])->name($peran.'.ujian.soal.unggah');
        Route::delete('ujian/{ujian}/soal/{index}', [UjianKelasController::class, 'hapusSoal'])->whereNumber('index')->name($peran.'.ujian.soal.hapus');
        Route::post('ujian/{ujian}/lembar-soal', [UjianKelasController::class, 'buatSoal'])->name($peran.'.ujian.lembar-soal');
    });
    Route::put('ujian/{ujian}/nilai/{mahasiswa}', [UjianKelasController::class, 'nilai'])->name($peran.'.ujian.nilai');
    Route::put('ujian/{ujian}/rilis-nilai', [UjianKelasController::class, 'rilisNilai'])->name($peran.'.ujian.rilis-nilai');
    Route::get('ujian/{ujian}/daftar-hadir', [UjianKelasController::class, 'daftarHadir'])->name($peran.'.ujian.daftar-hadir');
};

// Menu Jadwal Kelas/Materi/Tugas/Quiz sama untuk admin dan dosen: daftar lintas kelas + tambah dengan isian Kelas Kuliah.
$ruteMenuKonten = function (string $peran): void {
    foreach (['jadwal' => JadwalController::class, 'materi' => MateriController::class, 'tugas' => TugasController::class, 'quiz' => QuizController::class] as $menu => $controller) {
        // Jadwal tidak punya flag; materi/tugas/quiz ikut fitur per klien dengan nama yang sama.
        Route::middleware($menu === 'jadwal' ? ["can:{$peran}.{$menu}"] : ["fitur:{$menu}", "can:{$peran}.{$menu}"])->group(function () use ($peran, $menu, $controller): void {
            Route::get($menu, [$controller, 'index'])->name("{$peran}.{$menu}.index");
            Route::get("{$menu}/create", [$controller, 'createDariMenu'])->name("{$peran}.{$menu}.create");
            Route::post($menu, [$controller, 'storeDariMenu'])->name("{$peran}.{$menu}.store");
        });
    }
};

// Rute presensi sama untuk admin dan dosen; bedanya hanya prefix nama dan izin grup.
$rutePresensi = function (string $peran): void {
    Route::get('presensi', [PresensiController::class, 'index'])->name($peran.'.presensi.index');
    Route::get('presensi/kelas/{kelasKuliah}', [PresensiController::class, 'kelas'])->name($peran.'.presensi.kelas');
    Route::post('presensi/kelas/{kelasKuliah}/generate', [PresensiController::class, 'generate'])->name($peran.'.presensi.generate');
    Route::get('presensi/kelas/{kelasKuliah}/ekspor', [PresensiController::class, 'ekspor'])->name($peran.'.presensi.ekspor');
    Route::get('presensi/kelas/{kelasKuliah}/peserta-ujian', [PresensiController::class, 'pesertaUjian'])->name($peran.'.presensi.peserta-ujian');
    Route::post('presensi/kelas/{kelasKuliah}/dispensasi', [PresensiController::class, 'dispensasiSimpan'])->name($peran.'.presensi.dispensasi.simpan');
    Route::delete('presensi/dispensasi/{dispensasi}', [PresensiController::class, 'dispensasiHapus'])->name($peran.'.presensi.dispensasi.hapus');
    Route::get('presensi/izin', [PengajuanIzinController::class, 'index'])->name($peran.'.presensi.izin.index');
    Route::put('presensi/izin/{pengajuanIzin}', [PengajuanIzinController::class, 'proses'])->name($peran.'.presensi.izin.proses');
    Route::get('presensi/pertemuan/{pertemuan}', [PertemuanController::class, 'show'])->name($peran.'.presensi.pertemuan.show');
    Route::put('presensi/pertemuan/{pertemuan}', [PertemuanController::class, 'update'])->name($peran.'.presensi.pertemuan.update');
    Route::post('presensi/pertemuan/{pertemuan}/mulai', [PertemuanController::class, 'mulai'])->name($peran.'.presensi.pertemuan.mulai');
    Route::post('presensi/pertemuan/{pertemuan}/selesai', [PertemuanController::class, 'selesai'])->name($peran.'.presensi.pertemuan.selesai');
    Route::put('presensi/pertemuan/{pertemuan}/jurnal', [PertemuanController::class, 'jurnal'])->name($peran.'.presensi.pertemuan.jurnal');
    Route::put('presensi/pertemuan/{pertemuan}/mahasiswa', [PertemuanController::class, 'simpanPresensi'])->name($peran.'.presensi.pertemuan.mahasiswa');
    Route::get('presensi/pertemuan/{pertemuan}/bap', [BapController::class, 'pertemuan'])->name($peran.'.presensi.pertemuan.bap');
    Route::get('presensi/kelas/{kelasKuliah}/bap', [BapController::class, 'kelas'])->name($peran.'.presensi.bap');
    Route::middleware('fitur:presensi_qr')->group(function () use ($peran): void {
        Route::post('presensi/pertemuan/{pertemuan}/mandiri', [PertemuanController::class, 'bukaMandiri'])->name($peran.'.presensi.pertemuan.mandiri.buka');
        Route::delete('presensi/pertemuan/{pertemuan}/mandiri', [PertemuanController::class, 'tutupMandiri'])->name($peran.'.presensi.pertemuan.mandiri.tutup');
        Route::get('presensi/pertemuan/{pertemuan}/kode', [PertemuanController::class, 'kode'])->name($peran.'.presensi.pertemuan.kode');
    });
};

// `/dashboard` bukan halaman sendiri: mengalihkan ke beranda peran (admin/dosen/mahasiswa). Halaman umum hanya untuk
// akun yang tidak punya izin dashboard mana pun.
Route::get('dashboard', function (Request $request) {
    $home = $request->user()->homeRoute();

    return $home === 'dashboard' ? Inertia::render('Dashboard') : redirect()->route($home);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('admin', AdminDashboardController::class)
    ->middleware(['auth', 'verified', 'can:admin.dashboard'])
    ->name('admin.dashboard');

// Berkas kuliah disimpan di disk privat; hak akses dicek di BerkasController.
Route::prefix('berkas')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('materi/{materi}/{index}', [BerkasController::class, 'materi'])->whereNumber('index')->middleware('fitur:materi')->name('berkas.materi');
    Route::middleware('fitur:tugas')->group(function (): void {
        Route::get('tugas/{tugas}/{index}', [BerkasController::class, 'tugas'])->whereNumber('index')->name('berkas.tugas');
        Route::get('pengumpulan/{pengumpulan}/{index}', [BerkasController::class, 'pengumpulan'])->whereNumber('index')->name('berkas.pengumpulan');
    });
    Route::get('info-kuliah/{infoKuliah}', [BerkasController::class, 'infoKuliah'])->name('berkas.info-kuliah');
    Route::get('foto/{user}', [BerkasController::class, 'foto'])->name('berkas.foto');
    Route::get('mahasiswa/{user}/{jenis}', [BerkasController::class, 'mahasiswa'])->where('jenis', '[a-z_]+')->name('berkas.mahasiswa');
    Route::get('pmb/{cmb}/{jenis}', [BerkasController::class, 'pmb'])->where('jenis', '[a-z_]+')->name('berkas.pmb');
    Route::get('izin/{pengajuanIzin}/{index}', [BerkasController::class, 'izin'])->whereNumber('index')->name('berkas.izin');
    Route::middleware('fitur:keuangan')->group(function (): void {
        Route::get('bukti-remidi/{tagihanRemidi}', [BerkasController::class, 'buktiRemidi'])->name('berkas.bukti-remidi');
        Route::get('bukti-susulan/{tagihanSusulan}', [BerkasController::class, 'buktiSusulan'])->middleware('fitur:ujian_susulan')->name('berkas.bukti-susulan');
        Route::get('bukti-semester/{tagihanSemester}', [BerkasController::class, 'buktiSemester'])->name('berkas.bukti-semester');
    });
    Route::get('pengajuan-akademik/{pengajuanAkademik}/{kunci}', [BerkasController::class, 'pengajuanAkademik'])->where('kunci', '[a-z_]+')->name('berkas.pengajuan-akademik');
    Route::get('lampiran-susulan/{pengajuanSusulan}/{index}', [BerkasController::class, 'lampiranSusulan'])->whereNumber('index')->middleware('fitur:ujian_susulan')->name('berkas.lampiran-susulan');
    Route::get('surat-pendadaran/{pendadaran}', [PendadaranBerkasController::class, 'surat'])->name('berkas.surat-pendadaran');
    Route::get('naskah-revisi/{pendadaran}', [PendadaranBerkasController::class, 'naskahRevisi'])->name('berkas.naskah-revisi');
    Route::get('skl/{wisuda}', [PendadaranBerkasController::class, 'skl'])->name('berkas.skl');
    Route::get('naskah-ta/{tugasAkhir}', [PendadaranBerkasController::class, 'naskahTa'])->name('berkas.naskah-ta');
    Route::get('ujian-soal/{ujian}/{index}', [BerkasController::class, 'soalUjian'])->whereNumber('index')->name('berkas.ujian-soal');
    Route::get('ujian-jawaban/{jawaban}/{index}', [BerkasController::class, 'jawabanUjian'])->whereNumber('index')->name('berkas.ujian-jawaban');
});

// Kelola User & Kelola Role meminta konfirmasi kata sandi (berlaku 3 jam, config auth.password_timeout).
Route::prefix('admin/users')->middleware(['auth', 'verified'])->group(function () {
    foreach (['dosen', 'mahasiswa', 'karyawan'] as $type) {
        Route::middleware(['can:admin.users.'.$type, 'password.confirm'])->group(function () use ($type): void {
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
            Route::post($type.'/{user}/verifikasi-email', fn (Request $request, User $user) => app(UserController::class)->kirimVerifikasi($request, $type, $user))
                ->middleware('throttle:6,1')
                ->name('admin.users.'.$type.'.verifikasi-email');
            Route::put($type.'/{user}/tandai-terverifikasi', fn (Request $request, User $user) => app(UserController::class)->tandaiTerverifikasi($request, $type, $user))
                ->name('admin.users.'.$type.'.tandai-terverifikasi');
        });
    }
    Route::delete('mahasiswa/{user}/kunci-krs', [UserController::class, 'bukaKunciKrs'])
        ->middleware(['can:admin.users.mahasiswa', 'password.confirm'])
        ->name('admin.users.mahasiswa.buka-kunci-krs');
});

// Tools → Create User & Data Pengguna: pintu bersama ke kelola user per jenis (izin dicek per jenis di controller).
Route::prefix('admin/pengguna')->middleware(['auth', 'verified', 'password.confirm'])->group(function (): void {
    Route::get('/', [PenggunaController::class, 'index'])->name('admin.pengguna.index');
    Route::get('buat', [PenggunaController::class, 'buat'])->name('admin.pengguna.buat');
});

Route::prefix('admin')->middleware(['auth', 'verified'])->group(function () use ($rutePresensi, $ruteUjianKelas, $ruteMenuKonten): void {
    // Kelola Role hanya dibuka selama fitur kelola_role aktif; izin admin.roles tetap berlaku untuk mengelola akun.
    Route::middleware(['fitur:kelola_role', 'can:admin.roles', 'password.confirm'])->group(function (): void {
        Route::resource('roles', RoleController::class)->except('show')->names('admin.roles');
    });

    Route::middleware('can:admin.info-kuliah')->group(function (): void {
        Route::resource('info-kuliah', InfoKuliahController::class)->except('show')->parameters(['info-kuliah' => 'infoKuliah'])->names('admin.info-kuliah');
    });

    // Yapika: Jenis Biaya ikut fitur keuangan (mati → 404), karena pembayaran tidak ikut alur sistem.
    Route::middleware(['fitur:keuangan', 'can:admin.jenis-biaya'])->group(function (): void {
        Route::get('jenis-biaya', [JenisBiayaController::class, 'index'])->name('admin.jenis-biaya.index');
        Route::get('jenis-biaya/create', [JenisBiayaController::class, 'create'])->name('admin.jenis-biaya.create');
        Route::post('jenis-biaya', [JenisBiayaController::class, 'store'])->name('admin.jenis-biaya.store');
        Route::get('jenis-biaya/{jenisBiaya}/edit', [JenisBiayaController::class, 'edit'])->name('admin.jenis-biaya.edit');
        Route::put('jenis-biaya/{jenisBiaya}', [JenisBiayaController::class, 'update'])->name('admin.jenis-biaya.update');
        Route::delete('jenis-biaya/{jenisBiaya}', [JenisBiayaController::class, 'destroy'])->name('admin.jenis-biaya.destroy');
    });

    Route::middleware(['fitur:keuangan', 'can:admin.tagihan'])->group(function (): void {
        Route::get('tagihan', [TagihanController::class, 'index'])->name('admin.tagihan.index');
        Route::get('tagihan/{mahasiswa}/rincian', [TagihanController::class, 'rincian'])->name('admin.tagihan.rincian');
        Route::post('tagihan/terbitkan', [TagihanController::class, 'terbitkan'])->name('admin.tagihan.terbitkan');
        Route::post('tagihan/{tagihanSemester}/lunas', [TagihanController::class, 'lunas'])->name('admin.tagihan.lunas');
        Route::post('tagihan/{tagihanSemester}/tolak', [TagihanController::class, 'tolak'])->name('admin.tagihan.tolak');
        Route::post('tagihan/{tagihanSemester}/batal-lunas', [TagihanController::class, 'batalLunas'])->name('admin.tagihan.batal-lunas');
        Route::post('tagihan/{tagihanSemester}/bukti', [TagihanController::class, 'unggahBukti'])->name('admin.tagihan.bukti');
        Route::put('tagihan/{mahasiswa}/rincian', [TagihanController::class, 'simpanRincian'])->name('admin.tagihan.rincian.simpan');
        Route::delete('tagihan/{mahasiswa}/kunci-krs', [TagihanController::class, 'bukaKunciKrs'])->name('admin.tagihan.buka-kunci-krs');
        Route::get('tagihan-remidi', [TagihanRemidiController::class, 'index'])->name('admin.tagihan-remidi.index');
        Route::post('tagihan-remidi/terbitkan', [TagihanRemidiController::class, 'terbitkan'])->name('admin.tagihan-remidi.terbitkan');
        Route::middleware('fitur:ujian_susulan')->group(function (): void {
            Route::get('tagihan-susulan', [TagihanSusulanController::class, 'index'])->name('admin.tagihan-susulan.index');
            Route::post('tagihan-susulan/terbitkan', [TagihanSusulanController::class, 'terbitkan'])->name('admin.tagihan-susulan.terbitkan');
            Route::post('tagihan-susulan/{tagihanSusulan}/lunas', [TagihanSusulanController::class, 'lunas'])->name('admin.tagihan-susulan.lunas');
            Route::post('tagihan-susulan/{tagihanSusulan}/tolak', [TagihanSusulanController::class, 'tolak'])->name('admin.tagihan-susulan.tolak');
        });
        Route::post('tagihan-remidi/kunci-massal', [TagihanRemidiController::class, 'kunciMassal'])->name('admin.tagihan-remidi.kunci-massal');
        Route::post('tagihan-remidi/{tagihanRemidi}/lunas', [TagihanRemidiController::class, 'lunas'])->name('admin.tagihan-remidi.lunas');
        Route::post('tagihan-remidi/{tagihanRemidi}/tolak', [TagihanRemidiController::class, 'tolak'])->name('admin.tagihan-remidi.tolak');
    });

    Route::middleware('can:admin.ujian')->group(function () use ($ruteUjianKelas): void {
        Route::get('ujian', [AdminUjianController::class, 'index'])->name('admin.ujian.index');
        Route::middleware('fitur:ujian_susulan')->group(function (): void {
            Route::get('ujian-susulan', [AdminUjianSusulanController::class, 'index'])->name('admin.ujian-susulan.index');
            Route::post('ujian-susulan/{pengajuanSusulan}/setujui', [AdminUjianSusulanController::class, 'setujui'])->name('admin.ujian-susulan.setujui');
            Route::post('ujian-susulan/{pengajuanSusulan}/tolak', [AdminUjianSusulanController::class, 'tolak'])->name('admin.ujian-susulan.tolak');
            Route::post('ujian/susulan-massal', [AdminUjianController::class, 'buatSusulanMassal'])->name('admin.ujian.susulan-massal');
        });
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

    Route::middleware(['fitur:pindah_kelas', 'can:admin.pindah-kelas'])->group(function (): void {
        Route::get('pindah-kelas', [AdminPindahKelasController::class, 'index'])->name('admin.pindah-kelas.index');
        Route::put('pindah-kelas/{pengajuan}/approve', [AdminPindahKelasController::class, 'approve'])->name('admin.pindah-kelas.approve');
        Route::put('pindah-kelas/{pengajuan}/reject', [AdminPindahKelasController::class, 'reject'])->name('admin.pindah-kelas.reject');
    });

    Route::middleware('can:admin.pengajuan-akademik')->group(function (): void {
        Route::get('pengajuan-akademik', [AdminPengajuanAkademikController::class, 'index'])->name('admin.pengajuan-akademik.index');
        // Satu halaman per jenis di menu Pengajuan & Pendaftaran; alamat lama ?jenis=… dialihkan ke sini.
        Route::get('persetujuan-tugas-akhir', [AdminPengajuanAkademikController::class, 'index'])->defaults('jenis', 'tugas_akhir')->name('admin.persetujuan-ta.index');
        Route::get('pendaftaran-pendadaran', [AdminPengajuanAkademikController::class, 'index'])->defaults('jenis', 'pendadaran')->middleware('fitur:pendadaran')->name('admin.pendaftaran-pendadaran.index');
        Route::get('persetujuan-kkm', [AdminPengajuanAkademikController::class, 'index'])->defaults('jenis', 'kkm')->name('admin.persetujuan-kkm.index');
        Route::get('daftar-ppl', [AdminPengajuanAkademikController::class, 'index'])->defaults('jenis', 'ppl')->name('admin.daftar-ppl.index');
        Route::get('ujian-komprehensif', [AdminPengajuanAkademikController::class, 'index'])->defaults('jenis', 'kompre')->name('admin.pengajuan-kompre.index');
        Route::get('pendaftaran-sidang', [AdminPengajuanAkademikController::class, 'index'])->defaults('jenis', 'sidang')->middleware('fitur:!pendadaran')->name('admin.pendaftaran-sidang.index');
        Route::get('daftar-wisuda', [AdminPengajuanAkademikController::class, 'index'])->defaults('jenis', 'wisuda')->name('admin.daftar-wisuda.index');
        Route::get('gelombang-kompre', [GelombangKompreController::class, 'index'])->name('admin.gelombang-kompre.index');
        Route::post('gelombang-kompre', [GelombangKompreController::class, 'store'])->name('admin.gelombang-kompre.store');
        Route::put('gelombang-kompre/{gelombangKompre}', [GelombangKompreController::class, 'update'])->name('admin.gelombang-kompre.update');
        Route::delete('gelombang-kompre/{gelombangKompre}', [GelombangKompreController::class, 'destroy'])->name('admin.gelombang-kompre.destroy');
        Route::post('pengajuan-akademik/{pengajuanAkademik}/setujui', [AdminPengajuanAkademikController::class, 'setujui'])->name('admin.pengajuan-akademik.setujui');
        Route::post('pengajuan-akademik/{pengajuanAkademik}/perbaikan', [AdminPengajuanAkademikController::class, 'perbaikan'])->name('admin.pengajuan-akademik.perbaikan');
        Route::post('pengajuan-akademik/{pengajuanAkademik}/tolak', [AdminPengajuanAkademikController::class, 'tolak'])->name('admin.pengajuan-akademik.tolak');
        Route::get('periode-wisuda', [PeriodeWisudaController::class, 'index'])->name('admin.periode-wisuda.index');
        Route::post('periode-wisuda', [PeriodeWisudaController::class, 'store'])->name('admin.periode-wisuda.store');
        Route::get('periode-wisuda/{periodeWisuda}', [PeriodeWisudaController::class, 'show'])->name('admin.periode-wisuda.show');
        Route::put('periode-wisuda/{periodeWisuda}', [PeriodeWisudaController::class, 'update'])->name('admin.periode-wisuda.update');
        Route::delete('periode-wisuda/{periodeWisuda}', [PeriodeWisudaController::class, 'destroy'])->name('admin.periode-wisuda.destroy');
        Route::get('periode-wisuda/{periodeWisuda}/cetak', [PeriodeWisudaController::class, 'cetak'])->name('admin.periode-wisuda.cetak');
        Route::post('periode-wisuda/{periodeWisuda}/skl', [PeriodeWisudaController::class, 'sklMassal'])->name('admin.periode-wisuda.skl-massal');
        Route::post('wisuda/{wisuda}/skl', [PeriodeWisudaController::class, 'skl'])->name('admin.wisuda.skl');
    });

    Route::middleware('can:admin.verifikasi-krs')->group(function (): void {
        Route::get('verifikasi-krs', [VerifikasiKrsController::class, 'index'])->name('admin.verifikasi-krs.index');
        Route::post('verifikasi-krs/setujui', [VerifikasiKrsController::class, 'setujuiMassal'])->name('admin.verifikasi-krs.setujui-massal');
        Route::get('verifikasi-krs/{krsSemester}', [VerifikasiKrsController::class, 'show'])->name('admin.verifikasi-krs.show');
        Route::post('verifikasi-krs/{krsSemester}/setujui', [VerifikasiKrsController::class, 'setujui'])->name('admin.verifikasi-krs.setujui');
        Route::post('verifikasi-krs/{krsSemester}/revisi', [VerifikasiKrsController::class, 'revisi'])->name('admin.verifikasi-krs.revisi');
    });

    Route::middleware('can:admin.input-krs')->group(function (): void {
        Route::get('input-krs', [InputKrsController::class, 'index'])->name('admin.input-krs.index');
        Route::post('input-krs/{mahasiswa}/kelas/{kelasKuliah}', [InputKrsController::class, 'store'])->name('admin.input-krs.store');
        Route::delete('input-krs/krs/{krs}', [InputKrsController::class, 'destroy'])->name('admin.input-krs.destroy');
        Route::post('input-krs/{mahasiswa}/simpan', [InputKrsController::class, 'simpan'])->name('admin.input-krs.simpan');
    });

    Route::middleware('can:admin.status-krs')->group(function (): void {
        Route::get('status-krs', [StatusKrsController::class, 'index'])->name('admin.status-krs.index');
        Route::post('status-krs/{mahasiswa}/ya', [StatusKrsController::class, 'ya'])->name('admin.status-krs.ya');
        Route::post('status-krs/{mahasiswa}/tidak', [StatusKrsController::class, 'tidak'])->name('admin.status-krs.tidak');
    });

    Route::middleware('can:admin.cetak-kst')->group(function (): void {
        Route::get('cetak-kst', [CetakKrsController::class, 'kst'])->name('admin.cetak-kst.index');
        Route::get('cetak-kst/{mahasiswa}', [CetakKrsController::class, 'cetakKst'])->name('admin.cetak-kst.cetak');
    });

    Route::middleware('can:admin.kartu-ujian')->group(function (): void {
        Route::get('kartu-ujian', [CetakKrsController::class, 'kartuUjian'])->name('admin.kartu-ujian.index');
        Route::get('kartu-ujian/{mahasiswa}', [CetakKrsController::class, 'cetakKartuUjian'])->name('admin.kartu-ujian.cetak');
    });

    Route::get('rekap-krs', [RekapKrsController::class, 'index'])->middleware('can:admin.rekap-krs')->name('admin.rekap-krs.index');

    Route::middleware('can:admin.pengajuan-cuti')->group(function (): void {
        Route::get('pengajuan-cuti', [AdminPengajuanCutiController::class, 'index'])->name('admin.pengajuan-cuti.index');
        Route::get('mahasiswa-cuti', [AdminMahasiswaCutiController::class, 'index'])->name('admin.mahasiswa-cuti.index');
        Route::post('pengajuan-cuti/{pengajuanAkademik}/setujui', [AdminPengajuanCutiController::class, 'setujui'])->name('admin.pengajuan-cuti.setujui');
        Route::post('pengajuan-cuti/{pengajuanAkademik}/perbaikan', [AdminPengajuanCutiController::class, 'perbaikan'])->name('admin.pengajuan-cuti.perbaikan');
        Route::post('pengajuan-cuti/{pengajuanAkademik}/tolak', [AdminPengajuanCutiController::class, 'tolak'])->name('admin.pengajuan-cuti.tolak');
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

    Route::middleware('can:admin.bobot-nilai')->group(function (): void {
        Route::get('bobot-nilai', [BobotNilaiController::class, 'index'])->name('admin.bobot-nilai.index');
        Route::put('bobot-nilai/{programStudi}', [BobotNilaiController::class, 'update'])->name('admin.bobot-nilai.update');
        Route::delete('bobot-nilai/{programStudi}', [BobotNilaiController::class, 'destroy'])->name('admin.bobot-nilai.destroy');
    });

    Route::middleware('can:admin.prasyarat')->group(function (): void {
        Route::resource('mata-kuliah-prasyarat', PrasyaratController::class)->except('show')->parameters(['mata-kuliah-prasyarat' => 'prasyarat'])->names('admin.prasyarat');
    });

    Route::middleware('can:admin.predikat')->group(function (): void {
        Route::resource('predikat', PredikatController::class)->except('show')->parameters(['predikat' => 'predikat'])->names('admin.predikat');
    });

    Route::middleware('can:admin.komponen-nilai')->group(function (): void {
        Route::get('komponen-nilai', [KomponenNilaiController::class, 'index'])->name('admin.komponen-nilai.index');
        Route::put('komponen-nilai', [KomponenNilaiController::class, 'update'])->name('admin.komponen-nilai.update');
    });

    Route::middleware('can:admin.detail-nilai')->group(function (): void {
        Route::get('detail-nilai', [DetailNilaiController::class, 'index'])->name('admin.detail-nilai.index');
        Route::get('detail-nilai/{mahasiswa}', [DetailNilaiController::class, 'show'])->name('admin.detail-nilai.show');
    });

    Route::middleware('can:admin.pendataan-nilai')->group(function (): void {
        Route::get('pendataan-nilai', [PendataanNilaiController::class, 'index'])->name('admin.pendataan-nilai.index');
        Route::get('pendataan-nilai/{mahasiswa}', [PendataanNilaiController::class, 'show'])->name('admin.pendataan-nilai.show');
    });

    Route::middleware('can:admin.nilai-kkm')->group(function (): void {
        Route::get('nilai-kkm', [NilaiKkmController::class, 'index'])->name('admin.nilai-kkm.index');
        Route::put('nilai-kkm/{krs}', [NilaiKkmController::class, 'update'])->name('admin.nilai-kkm.update');
        Route::post('nilai-kkm/{mahasiswa}/krs', [NilaiKkmController::class, 'tambahKrs'])->name('admin.nilai-kkm.tambah-krs');
    });

    Route::middleware('can:admin.validasi-nilai')->group(function (): void {
        Route::get('validasi-nilai', [ValidasiNilaiController::class, 'index'])->name('admin.validasi-nilai.index');
        Route::get('validasi-nilai/{mahasiswa}', [ValidasiNilaiController::class, 'show'])->name('admin.validasi-nilai.show');
        Route::post('validasi-nilai/{mahasiswa}/validasi', [ValidasiNilaiController::class, 'validasi'])->name('admin.validasi-nilai.validasi');
        Route::post('validasi-nilai/{mahasiswa}/batal', [ValidasiNilaiController::class, 'batalValidasi'])->name('admin.validasi-nilai.batal');
    });

    Route::middleware('can:admin.khs')->group(function (): void {
        Route::get('khs', [KhsController::class, 'index'])->name('admin.khs.index');
        Route::get('khs/{mahasiswa}', [KhsController::class, 'show'])->name('admin.khs.show');
        Route::get('khs/{mahasiswa}/download', [KhsController::class, 'download'])->name('admin.khs.download');
    });

    Route::middleware('can:admin.transkrip-nilai')->group(function (): void {
        Route::get('transkrip-nilai', [TranskripNilaiController::class, 'index'])->name('admin.transkrip-nilai.index');
        Route::get('transkrip-nilai/{mahasiswa}', [TranskripNilaiController::class, 'show'])->name('admin.transkrip-nilai.show');
        Route::get('transkrip-nilai/{mahasiswa}/download', [TranskripNilaiController::class, 'download'])->name('admin.transkrip-nilai.download');
    });

    Route::middleware('can:admin.nilai-semester')->group(function (): void {
        Route::get('nilai-semester', [NilaiSemesterController::class, 'index'])->name('admin.nilai-semester.index');
        Route::get('nilai-semester/{kelasKuliah}', [NilaiSemesterController::class, 'show'])->name('admin.nilai-semester.show');
        Route::put('nilai-semester/{kelasKuliah}', [NilaiSemesterController::class, 'update'])->name('admin.nilai-semester.update');
    });

    Route::middleware('can:admin.batas-sks')->group(function (): void {
        Route::get('batas-sks', [BatasSksController::class, 'index'])->name('admin.batas-sks.index');
        Route::put('batas-sks/{programStudi}', [BatasSksController::class, 'update'])->name('admin.batas-sks.update');
        Route::delete('batas-sks/{programStudi}', [BatasSksController::class, 'destroy'])->name('admin.batas-sks.destroy');
    });

    // Izin diperiksa per jenis data di controller (Data Mahasiswa, Data Dosen, Mata Kuliah).
    Route::get('impor/{jenis}', [ImporDataController::class, 'index'])->name('admin.impor.index');
    Route::get('impor/{jenis}/template', [ImporDataController::class, 'template'])->name('admin.impor.template');
    Route::post('impor/{jenis}', [ImporDataController::class, 'store'])->middleware('throttle:10,1')->name('admin.impor.store');

    Route::get('log-aktivitas', [LogAktivitasController::class, 'index'])->middleware('can:admin.log-aktivitas')->name('admin.log-aktivitas.index');

    Route::middleware('can:admin.informasi-pmb')->group(function (): void {
        Route::get('informasi-pmb', [InformasiPmbController::class, 'edit'])->name('admin.informasi-pmb.edit');
        Route::put('informasi-pmb', [InformasiPmbController::class, 'update'])->name('admin.informasi-pmb.update');
    });

    Route::middleware('can:admin.rekap-presensi')->group(function (): void {
        Route::get('rekap-presensi-mahasiswa', [RekapPresensiMahasiswaController::class, 'index'])->name('admin.rekap-presensi.index');
        Route::get('rekap-presensi-mahasiswa/unduh', [RekapPresensiMahasiswaController::class, 'unduh'])->name('admin.rekap-presensi.unduh');
    });

    Route::middleware('can:admin.ketua-kelas')->group(function (): void {
        Route::get('ketua-kelas', [KetuaKelasController::class, 'index'])->name('admin.ketua-kelas.index');
        Route::put('ketua-kelas/{kelasKuliah}', [KetuaKelasController::class, 'update'])->name('admin.ketua-kelas.update');
    });

    Route::middleware('can:admin.kurikulum')->group(function (): void {
        Route::resource('kurikulum', KurikulumController::class)->parameters(['kurikulum' => 'kurikulum'])->names('admin.kurikulum');
        Route::post('kurikulum/{kurikulum}/mata-kuliah', [KurikulumController::class, 'tambahMataKuliah'])->name('admin.kurikulum.mata-kuliah.store');
        Route::put('kurikulum/{kurikulum}/mata-kuliah/{mataKuliah}', [KurikulumController::class, 'ubahMataKuliah'])->name('admin.kurikulum.mata-kuliah.update');
        Route::delete('kurikulum/{kurikulum}/mata-kuliah/{mataKuliah}', [KurikulumController::class, 'hapusMataKuliah'])->name('admin.kurikulum.mata-kuliah.destroy');
    });

    Route::middleware('can:admin.syarat-ujian')->group(function (): void {
        Route::get('syarat-ujian', [SyaratUjianController::class, 'index'])->name('admin.syarat-ujian.index');
        Route::put('syarat-ujian/umum', [SyaratUjianController::class, 'updateUmum'])->name('admin.syarat-ujian.update-umum');
        Route::put('syarat-ujian/{programStudi}', [SyaratUjianController::class, 'update'])->name('admin.syarat-ujian.update');
        Route::delete('syarat-ujian/{programStudi}', [SyaratUjianController::class, 'destroy'])->name('admin.syarat-ujian.destroy');
    });

    Route::middleware('can:admin.presensi-dosen')->group(function (): void {
        Route::get('presensi-dosen', [PresensiDosenController::class, 'index'])->name('admin.presensi-dosen.index');
        Route::get('presensi-dosen/rekap', [PresensiDosenController::class, 'rekap'])->name('admin.presensi-dosen.rekap');
        Route::get('presensi-dosen/per-kelas', [PresensiController::class, 'laporanDosen'])->name('admin.presensi.laporan-dosen');
        Route::put('presensi-dosen/{pertemuan}', [PresensiDosenController::class, 'update'])->name('admin.presensi-dosen.update');
    });

    Route::middleware('can:admin.verifikasi-presensi-dosen')->group(function (): void {
        Route::get('verifikasi-presensi-dosen', [VerifikasiPresensiDosenController::class, 'index'])->name('admin.verifikasi-presensi-dosen.index');
        Route::post('verifikasi-presensi-dosen/setujui', [VerifikasiPresensiDosenController::class, 'setujui'])->name('admin.verifikasi-presensi-dosen.setujui');
        Route::post('verifikasi-presensi-dosen/tolak', [VerifikasiPresensiDosenController::class, 'tolak'])->name('admin.verifikasi-presensi-dosen.tolak');
        Route::post('verifikasi-presensi-dosen/batal', [VerifikasiPresensiDosenController::class, 'batal'])->name('admin.verifikasi-presensi-dosen.batal');
    });

    Route::middleware('can:admin.penasehat-akademik')->group(function (): void {
        Route::get('penasehat-akademik', [PenasehatAkademikController::class, 'index'])->name('admin.penasehat-akademik.index');
        Route::put('penasehat-akademik', [PenasehatAkademikController::class, 'update'])->name('admin.penasehat-akademik.update');
    });

    Route::middleware('can:admin.ruang')->group(function (): void {
        Route::resource('ruang', RuangController::class)->parameters(['ruang' => 'ruang'])->names('admin.ruang');
    });

    Route::middleware('can:admin.agama')->group(function (): void {
        Route::resource('agama', AgamaController::class)->except('show')->parameters(['agama' => 'agama'])->names('admin.agama');
    });

    Route::middleware('can:admin.periode-pmb')->group(function (): void {
        Route::resource('periode-pmb', PengaturanPmbController::class)->except('show')->parameters(['periode-pmb' => 'pengaturanPmb'])->names('admin.periode-pmb');
    });

    Route::middleware('can:admin.pendaftar-pmb')->group(function (): void {
        Route::resource('pendaftar-pmb', PendaftarPmbController::class)->only(['index', 'show', 'update', 'destroy'])->parameters(['pendaftar-pmb' => 'cmb'])->names('admin.pendaftar-pmb');
        Route::post('pendaftar-pmb/{cmb}/salin', [PendaftarPmbController::class, 'salin'])->name('admin.pendaftar-pmb.salin');
    });

    Route::middleware('can:admin.provinsi')->group(function (): void {
        Route::resource('provinsi', ProvinsiController::class)->except('show')->parameters(['provinsi' => 'provinsi'])->names('admin.provinsi');
    });

    Route::middleware('can:admin.kota')->group(function (): void {
        Route::resource('kota', KotaController::class)->except('show')->parameters(['kota' => 'kota'])->names('admin.kota');
    });

    Route::middleware('can:admin.badan-hukum')->group(function (): void {
        Route::get('badan-hukum', [BadanHukumController::class, 'edit'])->name('admin.badan-hukum.edit');
        Route::put('badan-hukum', [BadanHukumController::class, 'update'])->name('admin.badan-hukum.update');
    });

    Route::middleware('can:admin.institusi')->group(function (): void {
        Route::get('perguruan-tinggi', [PerguruanTinggiController::class, 'edit'])->name('admin.perguruan-tinggi.edit');
        // POST + _method=put karena membawa unggahan logo.
        Route::put('perguruan-tinggi', [PerguruanTinggiController::class, 'update'])->name('admin.perguruan-tinggi.update');
    });

    Route::middleware('can:admin.tahun-akademik')->group(function (): void {
        Route::resource('tahun-akademik', TahunAkademikController::class)->except('show')->parameters(['tahun-akademik' => 'tahunAkademik'])->names('admin.tahun-akademik');
    });

    // Menu tersendiri untuk konten kelas, dengan filter lintas kelas; tambah dari menu memilih kelas lewat isian Kelas Kuliah.
    $ruteMenuKonten('admin');
    Route::middleware('can:admin.presensi')->group(function () use ($rutePresensi): void {
        $rutePresensi('admin');
        Route::put('presensi/kelas/{kelasKuliah}/jumlah', [PresensiController::class, 'ubahJumlah'])->name('admin.presensi.jumlah');
        // Laporan per kelas kini tab "Per Kelas" di menu Presensi Dosen.
        Route::permanentRedirect('presensi/laporan-dosen', '/admin/presensi-dosen/per-kelas');
    });

    Route::middleware('can:admin.kelas-kuliah')->group(function (): void {
        Route::resource('kelas-kuliah', KelasKuliahController::class)->parameters(['kelas_kuliah' => 'kelasKuliah'])->names('admin.kelas-kuliah');
        Route::put('kelas-kuliah/{kelasKuliah}/krs/{krs}/nilai', [KelasKuliahController::class, 'updateGrade'])->name('admin.kelas-kuliah.krs.nilai');
        Route::put('kelas-kuliah/{kelasKuliah}/nilai-komponen', [KelasKuliahController::class, 'updateNilaiKomponen'])->name('admin.kelas-kuliah.nilai-komponen');
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
        Route::get('kelas-kuliah/{kelasKuliah}/materi/create', [MateriController::class, 'create'])->middleware('fitur:materi')->name('admin.kelas-kuliah.materi.create');
        Route::post('kelas-kuliah/{kelasKuliah}/materi', [MateriController::class, 'store'])->middleware('fitur:materi')->name('admin.kelas-kuliah.materi.store');
        Route::get('kelas-kuliah/{kelasKuliah}/materi/{materi}/edit', [MateriController::class, 'edit'])->middleware('fitur:materi')->name('admin.kelas-kuliah.materi.edit');
        Route::put('kelas-kuliah/{kelasKuliah}/materi/{materi}', [MateriController::class, 'update'])->middleware('fitur:materi')->name('admin.kelas-kuliah.materi.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/materi/{materi}', [MateriController::class, 'destroy'])->middleware('fitur:materi')->name('admin.kelas-kuliah.materi.destroy');
        Route::post('kelas-kuliah/{kelasKuliah}/materi/{materi}/duplicate', [MateriController::class, 'duplicate'])->middleware('fitur:materi')->name('admin.kelas-kuliah.materi.duplicate');
        Route::get('kelas-kuliah/{kelasKuliah}/tugas/create', [TugasController::class, 'create'])->middleware('fitur:tugas')->name('admin.kelas-kuliah.tugas.create');
        Route::post('kelas-kuliah/{kelasKuliah}/tugas', [TugasController::class, 'store'])->middleware('fitur:tugas')->name('admin.kelas-kuliah.tugas.store');
        Route::get('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'show'])->middleware('fitur:tugas')->name('admin.kelas-kuliah.tugas.show');
        Route::put('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/pengumpulan/{pengumpulanTugas}/nilai', [TugasController::class, 'updateSubmissionGrade'])->middleware('fitur:tugas')->name('admin.kelas-kuliah.tugas.pengumpulan.nilai');
        Route::get('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/edit', [TugasController::class, 'edit'])->middleware('fitur:tugas')->name('admin.kelas-kuliah.tugas.edit');
        Route::put('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'update'])->middleware('fitur:tugas')->name('admin.kelas-kuliah.tugas.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'destroy'])->middleware('fitur:tugas')->name('admin.kelas-kuliah.tugas.destroy');
        Route::post('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/duplicate', [TugasController::class, 'duplicate'])->middleware('fitur:tugas')->name('admin.kelas-kuliah.tugas.duplicate');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/create', [QuizController::class, 'create'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.create');
        Route::post('kelas-kuliah/{kelasKuliah}/quiz', [QuizController::class, 'store'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.store');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'show'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.show');
        Route::post('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions', [QuizController::class, 'storeQuestions'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.questions.store');
        Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions/{question}', [QuizController::class, 'updateQuestion'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.questions.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions/{question}', [QuizController::class, 'destroyQuestion'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.questions.destroy');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/edit', [QuizController::class, 'edit'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.edit');
        Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'update'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'destroy'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.destroy');
        Route::post('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/duplicate', [QuizController::class, 'duplicate'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.duplicate');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/attempts/{attempt}', [QuizPenilaianController::class, 'show'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.attempts.show');
        Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/attempts/{attempt}/nilai', [QuizPenilaianController::class, 'grade'])->middleware('fitur.quiz')->name('admin.kelas-kuliah.quiz.attempts.grade');
    });
});

Route::prefix('dosen')->middleware(['auth', 'verified'])->group(function () use ($rutePresensi, $ruteUjianKelas, $ruteMenuKonten) {
    Route::middleware('can:dosen.dashboard')->group(function (): void {
        Route::get('/', DosenDashboardController::class)->name('dosen.dashboard');
        Route::get('profile', fn () => Inertia::render('DosenPlaceholder', ['title' => 'Profile']))->name('dosen.profile');
    });

    $ruteMenuKonten('dosen');
    // Dosen mengelola jadwal mingguan kelas yang diampunya dari menu Jadwal Kelas (halaman kelas dosen hanya menampilkannya).
    Route::middleware('can:dosen.jadwal')->group(function (): void {
        Route::get('kelas-kuliah/{kelasKuliah}/jadwal/{jadwal}/edit', [JadwalController::class, 'edit'])->name('dosen.kelas-kuliah.jadwal.edit');
        Route::put('kelas-kuliah/{kelasKuliah}/jadwal/{jadwal}', [JadwalController::class, 'update'])->name('dosen.kelas-kuliah.jadwal.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/jadwal/{jadwal}', [JadwalController::class, 'destroy'])->name('dosen.kelas-kuliah.jadwal.destroy');
    });
    Route::middleware('can:dosen.presensi')->group(fn () => $rutePresensi('dosen'));

    Route::middleware('can:dosen.kelas-kuliah')->group(function (): void {
        Route::get('kelas-kuliah', [KelasKuliahController::class, 'index'])->name('dosen.kelas-kuliah.index');
        // Menu Input Nilai: daftar kelas yang sama, tombolnya langsung membuka bagian Nilai Mahasiswa.
        Route::get('input-nilai', [KelasKuliahController::class, 'index'])->defaults('mode', 'nilai')->name('dosen.input-nilai.index');
        Route::get('kelas-kuliah/{kelasKuliah}', [KelasKuliahController::class, 'show'])->name('dosen.kelas-kuliah.show');
        Route::put('kelas-kuliah/{kelasKuliah}/krs/{krs}/nilai', [KelasKuliahController::class, 'updateGrade'])->name('dosen.kelas-kuliah.krs.nilai');
        Route::put('kelas-kuliah/{kelasKuliah}/nilai-komponen', [KelasKuliahController::class, 'updateNilaiKomponen'])->name('dosen.kelas-kuliah.nilai-komponen');
        Route::post('kelas-kuliah/{kelasKuliah}/finalisasi-nilai', [KelasKuliahController::class, 'finalisasiNilai'])->name('dosen.kelas-kuliah.finalisasi-nilai');
        Route::post('kelas-kuliah/{kelasKuliah}/remidi/kunci', [RemidiController::class, 'kunci'])->name('dosen.kelas-kuliah.remidi.kunci');
        Route::post('kelas-kuliah/{kelasKuliah}/remidi/finalisasi', [RemidiController::class, 'finalisasi'])->name('dosen.kelas-kuliah.remidi.finalisasi');
        Route::get('kelas-kuliah/{kelasKuliah}/materi/create', [MateriController::class, 'create'])->middleware('fitur:materi')->name('dosen.kelas-kuliah.materi.create');
        Route::post('kelas-kuliah/{kelasKuliah}/materi', [MateriController::class, 'store'])->middleware('fitur:materi')->name('dosen.kelas-kuliah.materi.store');
        Route::get('kelas-kuliah/{kelasKuliah}/materi/{materi}/edit', [MateriController::class, 'edit'])->middleware('fitur:materi')->name('dosen.kelas-kuliah.materi.edit');
        Route::put('kelas-kuliah/{kelasKuliah}/materi/{materi}', [MateriController::class, 'update'])->middleware('fitur:materi')->name('dosen.kelas-kuliah.materi.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/materi/{materi}', [MateriController::class, 'destroy'])->middleware('fitur:materi')->name('dosen.kelas-kuliah.materi.destroy');
        Route::post('kelas-kuliah/{kelasKuliah}/materi/{materi}/duplicate', [MateriController::class, 'duplicate'])->middleware('fitur:materi')->name('dosen.kelas-kuliah.materi.duplicate');
        Route::get('kelas-kuliah/{kelasKuliah}/tugas/create', [TugasController::class, 'create'])->middleware('fitur:tugas')->name('dosen.kelas-kuliah.tugas.create');
        Route::post('kelas-kuliah/{kelasKuliah}/tugas', [TugasController::class, 'store'])->middleware('fitur:tugas')->name('dosen.kelas-kuliah.tugas.store');
        Route::get('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'show'])->middleware('fitur:tugas')->name('dosen.kelas-kuliah.tugas.show');
        Route::put('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/pengumpulan/{pengumpulanTugas}/nilai', [TugasController::class, 'updateSubmissionGrade'])->middleware('fitur:tugas')->name('dosen.kelas-kuliah.tugas.pengumpulan.nilai');
        Route::get('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/edit', [TugasController::class, 'edit'])->middleware('fitur:tugas')->name('dosen.kelas-kuliah.tugas.edit');
        Route::put('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'update'])->middleware('fitur:tugas')->name('dosen.kelas-kuliah.tugas.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/tugas/{tugas}', [TugasController::class, 'destroy'])->middleware('fitur:tugas')->name('dosen.kelas-kuliah.tugas.destroy');
        Route::post('kelas-kuliah/{kelasKuliah}/tugas/{tugas}/duplicate', [TugasController::class, 'duplicate'])->middleware('fitur:tugas')->name('dosen.kelas-kuliah.tugas.duplicate');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/create', [QuizController::class, 'create'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.create');
        Route::post('kelas-kuliah/{kelasKuliah}/quiz', [QuizController::class, 'store'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.store');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'show'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.show');
        Route::post('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions', [QuizController::class, 'storeQuestions'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.questions.store');
        Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions/{question}', [QuizController::class, 'updateQuestion'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.questions.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/questions/{question}', [QuizController::class, 'destroyQuestion'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.questions.destroy');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/edit', [QuizController::class, 'edit'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.edit');
        Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'update'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.update');
        Route::delete('kelas-kuliah/{kelasKuliah}/quiz/{quiz}', [QuizController::class, 'destroy'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.destroy');
        Route::post('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/duplicate', [QuizController::class, 'duplicate'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.duplicate');
        Route::get('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/attempts/{attempt}', [QuizPenilaianController::class, 'show'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.attempts.show');
        Route::put('kelas-kuliah/{kelasKuliah}/quiz/{quiz}/attempts/{attempt}/nilai', [QuizPenilaianController::class, 'grade'])->middleware('fitur.quiz')->name('dosen.kelas-kuliah.quiz.attempts.grade');
        Route::redirect('jadwal-kuliah', '/dosen/kelas-kuliah', 301)->name('dosen.jadwal-kuliah');
    });

    Route::middleware('can:dosen.ujian')->group(function () use ($ruteUjianKelas): void {
        Route::get('ujian', [DosenUjianController::class, 'index'])->name('dosen.ujian.index');
        $ruteUjianKelas('dosen');
    });

    Route::get('pengajuan-pa', [DosenPengajuanMahasiswaPaController::class, 'index'])->middleware('can:dosen.pengajuan-pa')->name('dosen.pengajuan-pa.index');

    Route::middleware(['fitur:pendadaran', 'can:dosen.bimbingan'])->group(function (): void {
        Route::get('bimbingan', [BimbinganController::class, 'index'])->name('dosen.bimbingan.index');
        Route::post('bimbingan/pendadaran/{pengajuanAkademik}/setujui', [BimbinganController::class, 'setujui'])->name('dosen.bimbingan.pendadaran.setujui');
        Route::post('bimbingan/pendadaran/{pengajuanAkademik}/perbaikan', [BimbinganController::class, 'perbaikan'])->name('dosen.bimbingan.pendadaran.perbaikan');
        Route::post('bimbingan/pendadaran/{pengajuanAkademik}/tolak', [BimbinganController::class, 'tolak'])->name('dosen.bimbingan.pendadaran.tolak');
        Route::post('pendadaran/{pendadaran}/nilai', [DosenPendadaranController::class, 'nilai'])->name('dosen.pendadaran.nilai');
        Route::post('pendadaran/{pendadaran}/hasil', [DosenPendadaranController::class, 'hasil'])->name('dosen.pendadaran.hasil');
        Route::post('pendadaran/{pendadaran}/revisi/sahkan', [DosenPendadaranController::class, 'sahkanRevisi'])->name('dosen.pendadaran.revisi.sahkan');
        Route::post('pendadaran/{pendadaran}/revisi/tolak', [DosenPendadaranController::class, 'tolakRevisi'])->name('dosen.pendadaran.revisi.tolak');
    });

    Route::middleware('can:dosen.mahasiswa-kelas')->group(function (): void {
        Route::get('mahasiswa-kelas', [MahasiswaKelasController::class, 'index'])->name('dosen.mahasiswa-kelas');
    });
});

Route::prefix('mahasiswa')->middleware(['auth', 'verified'])->group(function () {
    Route::middleware('can:mahasiswa.dashboard')->group(function (): void {
        Route::get('/', MahasiswaDashboardController::class)->name('mahasiswa.dashboard');

        foreach ([
            'profile' => 'Profile', 'info-perkuliahan' => 'Info Perkuliahan',
        ] as $path => $title) {
            Route::get($path, fn () => Inertia::render('MahasiswaPlaceholder', ['title' => $title]))
                ->name('mahasiswa.'.$path);
        }
    });

    Route::middleware(['fitur:keuangan', 'can:mahasiswa.info-biaya'])->group(function (): void {
        Route::get('info-biaya-kuliah', [InfoBiayaKuliahController::class, 'index'])->name('mahasiswa.info-biaya-kuliah');
        Route::post('tagihan-semester/{tagihanSemester}/bukti', [MahasiswaTagihanSemesterController::class, 'unggahBukti'])->name('mahasiswa.tagihan-semester.bukti');
        Route::post('tagihan-remidi/{tagihanRemidi}/bukti', [MahasiswaTagihanRemidiController::class, 'unggahBukti'])->name('mahasiswa.tagihan-remidi.bukti');
        Route::post('tagihan-susulan/{tagihanSusulan}/bukti', [MahasiswaTagihanSusulanController::class, 'unggahBukti'])->middleware('fitur:ujian_susulan')->name('mahasiswa.tagihan-susulan.bukti');
    });

    Route::middleware('can:mahasiswa.info-kuliah')->group(function (): void {
        Route::get('info-kuliah', [MahasiswaInfoKuliahController::class, 'index'])->name('mahasiswa.info-kuliah');
    });

    Route::middleware(['can:mahasiswa.krs', 'tagihan.lunas'])->group(function (): void {
        Route::get('krs', [KrsController::class, 'index'])->name('mahasiswa.krs');
        Route::get('krs/download', [KrsController::class, 'download'])->name('mahasiswa.krs.download');
        Route::get('krs/kst', [KrsController::class, 'kst'])->name('mahasiswa.krs.kst');
        Route::get('cetak-kst', [CetakKartuController::class, 'kst'])->name('mahasiswa.cetak-kst');
        Route::post('krs/simpan', [KrsController::class, 'simpan'])->name('mahasiswa.krs.simpan');
        Route::post('krs/{kelasKuliah}', [KrsController::class, 'store'])->name('mahasiswa.krs.store');
        Route::delete('krs/{krs}', [KrsController::class, 'destroy'])->name('mahasiswa.krs.destroy');
    });

    Route::middleware('can:mahasiswa.hasil-studi')->group(function (): void {
        Route::get('hasil-studi', [HasilStudiController::class, 'index'])->name('mahasiswa.hasil-studi');
        Route::get('hasil-studi/download', [HasilStudiController::class, 'downloadKhs'])->name('mahasiswa.hasil-studi.download');
        Route::get('transkrip', [HasilStudiController::class, 'transkrip'])->name('mahasiswa.transkrip');
        Route::get('transkrip/download', [HasilStudiController::class, 'downloadTranskrip'])->name('mahasiswa.transkrip.download');
        Route::get('khs/transkrip-nilai', [HasilStudiController::class, 'transkrip'])->name('mahasiswa.khs.transkrip-nilai');
        Route::get('khs', [HasilStudiController::class, 'index'])->name('mahasiswa.khs');
    });

    Route::middleware('can:mahasiswa.presensi')->group(function (): void {
        Route::get('presensi', [MahasiswaPresensiController::class, 'index'])->name('mahasiswa.presensi');
        Route::middleware('fitur:presensi_qr')->group(function (): void {
            Route::get('presensi/masuk/{pertemuan}', [MahasiswaPresensiController::class, 'masuk'])->name('mahasiswa.presensi.masuk');
            Route::post('presensi', [MahasiswaPresensiController::class, 'checkIn'])->middleware('throttle:10,1')->name('mahasiswa.presensi.check-in');
        });
        Route::post('presensi/izin', [MahasiswaPresensiController::class, 'ajukanIzin'])->name('mahasiswa.presensi.izin');
    });

    Route::middleware('can:mahasiswa.ujian')->group(function (): void {
        Route::get('ujian', [MahasiswaUjianController::class, 'index'])->name('mahasiswa.ujian');
        Route::get('ujian/kartu', [MahasiswaUjianController::class, 'kartu'])->name('mahasiswa.ujian.kartu');
        Route::get('cetak-kartu-ujian', [CetakKartuController::class, 'kartuUjian'])->name('mahasiswa.cetak-kartu-ujian');
        Route::middleware('fitur:ujian_susulan')->group(function (): void {
            Route::post('ujian/{ujian}/susulan', [MahasiswaUjianSusulanController::class, 'ajukan'])->middleware('throttle:10,1')->name('mahasiswa.ujian.susulan');
            Route::delete('ujian-susulan/{pengajuanSusulan}', [MahasiswaUjianSusulanController::class, 'batalkan'])->name('mahasiswa.ujian-susulan.batalkan');
        });
        Route::get('ujian/{ujian}', [MahasiswaUjianController::class, 'show'])->name('mahasiswa.ujian.show');
        Route::post('ujian/{ujian}/jawaban', [MahasiswaUjianController::class, 'kumpulkan'])->middleware(['fitur:ujian_online', 'throttle:20,1'])->name('mahasiswa.ujian.kumpulkan');
    });

    Route::middleware('can:mahasiswa.pengajuan-cuti')->group(function (): void {
        Route::get('pengajuan-cuti', [MahasiswaPengajuanCutiController::class, 'index'])->name('mahasiswa.pengajuan-cuti');
        Route::post('pengajuan-cuti', [MahasiswaPengajuanCutiController::class, 'ajukan'])->middleware('throttle:10,1')->name('mahasiswa.pengajuan-cuti.ajukan');
        Route::post('pengajuan-cuti/aktif-kembali', [MahasiswaPengajuanCutiController::class, 'ajukanAktifKembali'])->middleware('throttle:10,1')->name('mahasiswa.pengajuan-cuti.aktif-kembali');
    });
    Route::middleware('can:mahasiswa.pengajuan-kegiatan')->group(function (): void {
        Route::get('pengajuan-kegiatan', [MahasiswaPengajuanKegiatanController::class, 'index'])->name('mahasiswa.pengajuan-kegiatan');
        Route::get('pengajuan-kkm', [MahasiswaPengajuanKegiatanController::class, 'index'])->defaults('jenis', 'kkm')->name('mahasiswa.pengajuan-kkm');
        Route::get('pengajuan-ppl', [MahasiswaPengajuanKegiatanController::class, 'index'])->defaults('jenis', 'ppl')->name('mahasiswa.pengajuan-ppl');
        Route::get('pengajuan-kompre', [MahasiswaPengajuanKegiatanController::class, 'index'])->defaults('jenis', 'kompre')->name('mahasiswa.pengajuan-kompre');
        Route::get('pendaftaran-sidang', [MahasiswaPengajuanKegiatanController::class, 'index'])->defaults('jenis', 'sidang')->middleware('fitur:!pendadaran')->name('mahasiswa.pendaftaran-sidang');
        Route::post('pengajuan-kegiatan/{jenis}', [MahasiswaPengajuanKegiatanController::class, 'ajukan'])->whereIn('jenis', ['kkm', 'ppl', 'kompre', 'sidang'])
            ->middleware('throttle:10,1')->name('mahasiswa.pengajuan-kegiatan.ajukan');
    });
    Route::middleware('can:mahasiswa.tugas-akhir')->group(function (): void {
        Route::get('tugas-akhir', [MahasiswaTugasAkhirController::class, 'index'])->name('mahasiswa.tugas-akhir');
        Route::get('wisuda', [MahasiswaTugasAkhirController::class, 'wisuda'])->name('mahasiswa.wisuda');
        Route::post('tugas-akhir/naskah', [MahasiswaTugasAkhirController::class, 'unggahNaskah'])->middleware('throttle:10,1')->name('mahasiswa.tugas-akhir.naskah');
        Route::post('tugas-akhir/pengajuan-ta', [MahasiswaTugasAkhirController::class, 'ajukanTa'])->middleware('throttle:10,1')->name('mahasiswa.tugas-akhir.ajukan-ta');
        Route::post('tugas-akhir/pendadaran', [MahasiswaTugasAkhirController::class, 'ajukanPendadaran'])->middleware(['fitur:pendadaran', 'throttle:10,1'])->name('mahasiswa.tugas-akhir.ajukan-pendadaran');
        Route::post('tugas-akhir/wisuda', [MahasiswaTugasAkhirController::class, 'ajukanWisuda'])->middleware('throttle:10,1')->name('mahasiswa.tugas-akhir.ajukan-wisuda');
        Route::post('tugas-akhir/revisi', [MahasiswaTugasAkhirController::class, 'unggahRevisi'])->middleware(['fitur:pendadaran', 'throttle:10,1'])->name('mahasiswa.tugas-akhir.revisi');
    });

    Route::middleware(['fitur:pindah_kelas', 'can:mahasiswa.pindah-kelas'])->group(function (): void {
        Route::get('pindah-kelas', [MahasiswaPindahKelasController::class, 'index'])->name('mahasiswa.pindah-kelas');
        Route::post('pindah-kelas', [MahasiswaPindahKelasController::class, 'store'])->name('mahasiswa.pindah-kelas.store');
    });

    Route::middleware('can:mahasiswa.jadwal-kuliah')->group(function (): void {
        Route::middleware('fitur:tugas')->group(function (): void {
            Route::get('tugas/{tugas}', [PengumpulanTugasController::class, 'show'])->name('mahasiswa.tugas.show');
            Route::post('tugas/{tugas}/pengumpulan', [PengumpulanTugasController::class, 'store'])->name('mahasiswa.tugas.pengumpulan.store');
        });
        Route::get('materi/{materi}', fn (Request $request, Materi $materi) => app(MahasiswaContentController::class)->materiShow($request, $materi))->middleware('fitur:materi')->name('mahasiswa.materi.show');
        // Lembar soal ujian online memakai rute quiz yang sama (lihat PastikanFiturQuiz).
        Route::middleware('fitur.quiz')->group(function (): void {
            Route::get('quiz/{quiz}', fn (Request $request, Quiz $quiz) => app(MahasiswaContentController::class)->quizShow($request, $quiz))->name('mahasiswa.quiz.show');
            Route::post('quiz/{quiz}/start', [QuizAttemptController::class, 'start'])->name('mahasiswa.quiz.start');
            Route::post('quiz/{quiz}/answers', [QuizAttemptController::class, 'saveAnswers'])->middleware('throttle:60,1')->name('mahasiswa.quiz.answers');
            Route::post('quiz/{quiz}/submit', [QuizAttemptController::class, 'submit'])->name('mahasiswa.quiz.submit');
        });
        Route::get('jadwal', fn (Request $request) => app(MahasiswaContentController::class)->jadwalKuliah($request))
            ->name('mahasiswa.jadwal-kuliah');
        Route::get('jadwal/{kelasKuliah}', fn (Request $request, KelasKuliah $kelasKuliah) => app(MahasiswaContentController::class)->show($request, $kelasKuliah))
            ->name('mahasiswa.jadwal-kuliah.show');
    });

});

require __DIR__.'/pmb.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
