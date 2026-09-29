<?php

namespace App\Http\Middleware;

use App\Models\PengaturanInstitusi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pakai zona waktu dari Pengaturan Sistem > Institusi sebelum middleware lain membaca jam.
 */
class TerapkanZonaWaktu
{
    public function handle(Request $request, Closure $next): Response
    {
        PengaturanInstitusi::terapkanZonaWaktu();

        return $next($request);
    }
}
