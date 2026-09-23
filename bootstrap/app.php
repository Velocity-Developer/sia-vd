<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PastikanTagihanLunas;
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
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            // Sesi terikat ke kata sandi: begitu kata sandi diubah (oleh pemilik akun maupun admin),
            // sesi lain yang masih terbuka ikut berakhir.
            AuthenticateSession::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias(['tagihan.lunas' => PastikanTagihanLunas::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
