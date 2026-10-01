<?php

use App\Http\Controllers\Admin\PengaturanAkademikController;
use App\Http\Controllers\Settings\EmailController;
use App\Http\Controllers\Settings\InstitusiController;
use App\Http\Controllers\Settings\MaintenanceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PengaturanSistemController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RecaptchaController;
use App\Http\Controllers\Settings\TampilanController;
use App\Http\Controllers\Settings\TemplateEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    // Pengaturan Sistem: satu halaman bertab, tiap tab beralamat sendiri dan diperiksa izinnya sendiri.
    Route::prefix('pengaturan-sistem')->middleware('verified')->group(function (): void {
        Route::get('/', [PengaturanSistemController::class, 'index'])->name('pengaturan-sistem.index');

        Route::middleware('can:admin.institusi')->group(function (): void {
            Route::get('institusi', [InstitusiController::class, 'edit'])->name('pengaturan-sistem.institusi');
            Route::put('institusi', [InstitusiController::class, 'update'])->name('institusi.update');
        });

        Route::middleware('can:admin.pengaturan-email')->group(function (): void {
            Route::get('email', [EmailController::class, 'edit'])->name('pengaturan-sistem.email');
            Route::put('email', [EmailController::class, 'update'])->name('pengaturan-email.update');
            Route::post('email/uji', [EmailController::class, 'uji'])
                ->middleware('throttle:6,1')
                ->name('pengaturan-email.uji');
            Route::put('email/template/{jenis}', [TemplateEmailController::class, 'update'])->name('pengaturan-email.template.update');
            Route::delete('email/template/{jenis}', [TemplateEmailController::class, 'destroy'])->name('pengaturan-email.template.destroy');
            Route::post('email/template/{jenis}/pratinjau', [TemplateEmailController::class, 'pratinjau'])->name('pengaturan-email.template.pratinjau');
        });

        Route::middleware('can:admin.pengaturan-akademik')->group(function (): void {
            Route::get('akademik', [PengaturanAkademikController::class, 'index'])->name('pengaturan-sistem.akademik');
            Route::put('akademik/batas-sks', [PengaturanAkademikController::class, 'updateBatasSks'])->name('admin.pengaturan-akademik.batas-sks');
            Route::put('akademik/skala-nilai', [PengaturanAkademikController::class, 'updateSkalaNilai'])->name('admin.pengaturan-akademik.skala-nilai');
            Route::put('akademik/kunci-krs', [PengaturanAkademikController::class, 'updateKunciKrs'])->middleware('fitur:keuangan')->name('admin.pengaturan-akademik.kunci-krs');
            Route::put('akademik/verifikasi-krs', [PengaturanAkademikController::class, 'updateVerifikasiKrs'])->name('admin.pengaturan-akademik.verifikasi-krs');
            Route::put('akademik/presensi', [PengaturanAkademikController::class, 'updatePresensi'])->name('admin.pengaturan-akademik.presensi');
            Route::put('akademik/pindah-kelas', [PengaturanAkademikController::class, 'updatePindahKelas'])->middleware('fitur:pindah_kelas')->name('admin.pengaturan-akademik.pindah-kelas');
            Route::put('akademik/remidi', [PengaturanAkademikController::class, 'updateRemidi'])->name('admin.pengaturan-akademik.remidi');
            Route::put('akademik/susulan', [PengaturanAkademikController::class, 'updateSusulan'])->middleware('fitur:ujian_susulan')->name('admin.pengaturan-akademik.susulan');
            Route::put('akademik/tugas-akhir', [PengaturanAkademikController::class, 'updateTugasAkhir'])->name('admin.pengaturan-akademik.tugas-akhir');
            Route::put('akademik/cuti', [PengaturanAkademikController::class, 'updateCuti'])->name('admin.pengaturan-akademik.cuti');
        });

        Route::middleware('can:admin.pengaturan-tampilan')->group(function (): void {
            Route::get('tampilan', [TampilanController::class, 'edit'])->name('pengaturan-sistem.tampilan');
            // POST karena membawa unggahan berkas.
            Route::post('tampilan', [TampilanController::class, 'update'])->name('pengaturan-tampilan.update');
        });

        Route::middleware('can:admin.pengaturan-recaptcha')->group(function (): void {
            Route::get('recaptcha', [RecaptchaController::class, 'edit'])->name('pengaturan-sistem.recaptcha');
            Route::put('recaptcha', [RecaptchaController::class, 'update'])->name('pengaturan-recaptcha.update');
        });

        Route::middleware('can:admin.pengaturan-maintenance')->group(function (): void {
            Route::get('maintenance', [MaintenanceController::class, 'edit'])->name('pengaturan-sistem.maintenance');
            Route::put('maintenance', [MaintenanceController::class, 'update'])->name('pengaturan-maintenance.update');
        });
    });

    // Alamat lama dialihkan agar tautan/bookmark yang sudah ada tetap berfungsi.
    Route::redirect('settings/institusi', '/pengaturan-sistem/institusi', 301);
    Route::redirect('settings/email', '/pengaturan-sistem/email', 301);
    Route::redirect('admin/pengaturan-akademik', '/pengaturan-sistem/akademik', 301);
});
