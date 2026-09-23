<?php

use App\Http\Controllers\Settings\EmailController;
use App\Http\Controllers\Settings\InstitusiController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('can:admin.institusi')->group(function (): void {
        Route::get('settings/institusi', [InstitusiController::class, 'edit'])->name('institusi.edit');
        Route::put('settings/institusi', [InstitusiController::class, 'update'])->name('institusi.update');
    });

    Route::middleware('can:admin.pengaturan-email')->group(function (): void {
        Route::get('settings/email', [EmailController::class, 'edit'])->name('pengaturan-email.edit');
        Route::put('settings/email', [EmailController::class, 'update'])->name('pengaturan-email.update');
        Route::post('settings/email/uji', [EmailController::class, 'uji'])
            ->middleware('throttle:6,1')
            ->name('pengaturan-email.uji');
    });
});
