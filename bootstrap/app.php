<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PastikanAkunAktif;
use App\Http\Middleware\PastikanDeveloper;
use App\Http\Middleware\PastikanFiturAktif;
use App\Http\Middleware\PastikanFiturQuiz;
use App\Http\Middleware\PastikanTagihanLunas;
use App\Http\Middleware\TahanSaatMaintenance;
use App\Http\Middleware\TerapkanZonaWaktu;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            if (config('app.dev_panel')) {
                require base_path('routes/dev.php');
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Zona waktu institusi dipasang paling awal agar semua middleware sesudahnya memakai jam yang sama.
        $middleware->web(prepend: [TerapkanZonaWaktu::class]);

        $middleware->web(append: [
            // Sesi terikat ke kata sandi: begitu kata sandi diubah (oleh pemilik akun maupun admin),
            // sesi lain yang masih terbuka ikut berakhir.
            AuthenticateSession::class,
            PastikanAkunAktif::class,
            HandleInertiaRequests::class,
            TahanSaatMaintenance::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias(['tagihan.lunas' => PastikanTagihanLunas::class, 'fitur' => PastikanFiturAktif::class, 'fitur.quiz' => PastikanFiturQuiz::class, 'developer' => PastikanDeveloper::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
