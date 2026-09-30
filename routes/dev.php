<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Dev\FiturController;
use Illuminate\Support\Facades\Route;

/*
 * Panel developer. Hanya didaftarkan bila DEV_PANEL=true (lihat bootstrap/app.php dan config/app.php).
 */
Route::middleware(['web', 'auth', 'verified', 'developer', 'password.confirm'])->prefix('dev')->name('dev.')->group(function (): void {
    Route::get('fitur', [FiturController::class, 'index'])->name('fitur.index');
    Route::put('fitur/{nama}', [FiturController::class, 'update'])->name('fitur.update');

    // Kelola Role milik developer: selalu tersedia di sini, terlepas dari fitur kelola_role untuk admin.
    Route::resource('roles', RoleController::class)->except('show')->names('roles');
});
