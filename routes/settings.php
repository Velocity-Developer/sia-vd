<?php

use App\Http\Controllers\Admin\PengaturanAkademikController;
use App\Http\Controllers\Settings\EmailController;
use App\Http\Controllers\Settings\InstitusiController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PengaturanSistemController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TampilanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    // Pengaturan Sistem: satu halaman bertab, tiap tab beralamat sendiri dan diperiksa izinnya sendiri.
    Route::prefix('pengaturan-sistem')->group(function (): void {
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
        });

        Route::middleware('can:admin.pengaturan-akademik')->group(function (): void {
            Route::get('akademik', [PengaturanAkademikController::class, 'index'])->name('pengaturan-sistem.akademik');
            Route::put('akademik/batas-sks', [PengaturanAkademikController::class, 'updateBatasSks'])->name('admin.pengaturan-akademik.batas-sks');
            Route::put('akademik/skala-nilai', [PengaturanAkademikController::class, 'updateSkalaNilai'])->name('admin.pengaturan-akademik.skala-nilai');
            Route::put('akademik/kunci-krs', [PengaturanAkademikController::class, 'updateKunciKrs'])->name('admin.pengaturan-akademik.kunci-krs');
            Route::put('akademik/presensi', [PengaturanAkademikController::class, 'updatePresensi'])->name('admin.pengaturan-akademik.presensi');
            Route::put('akademik/pindah-kelas', [PengaturanAkademikController::class, 'updatePindahKelas'])->name('admin.pengaturan-akademik.pindah-kelas');
        });

        Route::middleware('can:admin.pengaturan-tampilan')->group(function (): void {
            Route::get('tampilan', [TampilanController::class, 'edit'])->name('pengaturan-sistem.tampilan');
            // POST karena membawa unggahan berkas.
            Route::post('tampilan', [TampilanController::class, 'update'])->name('pengaturan-tampilan.update');
        });
    });

    // Alamat lama dialihkan agar tautan/bookmark yang sudah ada tetap berfungsi.
    Route::redirect('settings/institusi', '/pengaturan-sistem/institusi', 301);
    Route::redirect('settings/email', '/pengaturan-sistem/email', 301);
    Route::redirect('admin/pengaturan-akademik', '/pengaturan-sistem/akademik', 301);
});
