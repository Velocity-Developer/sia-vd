<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sesi yang sudah terbuka ikut berakhir begitu dosen dinonaktifkan atau status mahasiswa
 * bukan lagi Aktif (aturan yang sama dengan saat masuk, lihat User::alasanTidakBolehMasuk).
 */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ($alasan = $user->alasanTidakBolehMasuk()) === null) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag(['username' => $alasan])));

        // Muat ulang penuh agar daftar route peran ikut dibuang dari browser (sama seperti logout).
        return Inertia::location(route('login'));
    }
}
