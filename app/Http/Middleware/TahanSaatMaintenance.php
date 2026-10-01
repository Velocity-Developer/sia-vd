<?php

namespace App\Http\Middleware;

use App\Models\PengaturanMaintenance;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dosen/mahasiswa yang sesinya masih terbuka melihat halaman maintenance di halaman mana pun
 * selama mode maintenance aktif untuk jenis penggunanya. Keluar tetap bisa.
 *
 * Dipasang sesudah HandleInertiaRequests agar halaman maintenance ikut membawa data bersama
 * (nama institusi, tampilan).
 */
class TahanSaatMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PengaturanMaintenance::menghalangi($request->user()) || $request->routeIs('logout')) {
            return $next($request);
        }

        $status = PengaturanMaintenance::shared();

        // Kunjungan Inertia dialihkan ke muat ulang penuh, sehingga halaman maintenance tampil sebagai
        // kunjungan pertama berstatus 503 (bukan jendela galat Inertia).
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->isMethod('GET') ? $request->fullUrl() : url('/'));
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $status['pesan'], 'maintenance' => true], 503);
        }

        return Inertia::render('Maintenance', ['maintenance' => $status])
            ->toResponse($request)
            ->setStatusCode(503)
            ->header('Retry-After', '600');
    }
}
