<?php

namespace App\Http\Middleware;

use App\LingkupProdi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun Prodi hanya boleh melihat data global (Tahun Akademik, Ruang, Predikat) dan tidak mengisi presensi dosen,
 * walau izin menunya dimiliki. Pembatasan data per prodi sendiri ada di model (Models\Concerns\DibatasiProdi).
 */
class BatasiAksiProdi
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(LingkupProdi::aktif() && $request->routeIs(...LingkupProdi::RUTE_TERLARANG), 403, 'Akun Prodi hanya dapat melihat data ini.');

        return $next($request);
    }
}
