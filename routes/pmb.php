<?php

use App\Http\Controllers\Pmb\InformasiController;
use App\Http\Controllers\Pmb\PendaftaranController;
use Illuminate\Support\Facades\Route;

// Informasi dan formulir PMB publik (tanpa akun); formulir hanya menerima isian selama ada periode yang dibuka.
Route::prefix('pmb')->group(function (): void {
    Route::get('/', InformasiController::class)->name('pmb.informasi');
    Route::get('daftar', [PendaftaranController::class, 'create'])->name('pmb.daftar');
    Route::post('daftar', [PendaftaranController::class, 'store'])->middleware('throttle:10,1')->name('pmb.daftar.store');
    Route::get('selesai', [PendaftaranController::class, 'selesai'])->name('pmb.selesai');
    Route::get('kecamatan', [PendaftaranController::class, 'kecamatan'])->middleware('throttle:120,1')->name('pmb.kecamatan');
});
