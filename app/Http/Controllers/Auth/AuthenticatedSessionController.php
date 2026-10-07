<?php

namespace App\Http\Controllers\Auth;

use App\CaptchaGambar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'captchaUrl' => route('captcha', absolute: false),
        ]);
    }

    /**
     * Gambar captcha halaman masuk; setiap permintaan membuat kode baru di sesi.
     */
    public function captcha(CaptchaGambar $captcha): SymfonyResponse
    {
        return response($captcha->baru(), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $tujuan = redirect()->intended(route($request->user()->homeRoute(), absolute: false))->getTargetUrl();

        // Daftar route Ziggy ditanam di Blade sesuai izin pengguna (lihat config/ziggy.php),
        // jadi sesudah masuk halaman harus dimuat ulang penuh agar route peran ikut terkirim.
        return Inertia::location($tujuan);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Sama seperti saat masuk: daftar route milik peran harus dibuang dari browser.
        return Inertia::location(url('/'));
    }
}
