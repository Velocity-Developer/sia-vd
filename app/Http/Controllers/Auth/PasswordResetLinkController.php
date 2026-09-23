<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Halaman permintaan tautan atur ulang kata sandi.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Kirim tautan atur ulang kata sandi ke surel pemilik akun.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], attributes: ['email' => 'Email']);

        Password::sendResetLink($request->only('email'));

        // Jawaban selalu sama agar tidak bisa dipakai menebak surel yang terdaftar.
        return back()->with('status', 'Jika email tersebut terdaftar, tautan atur ulang kata sandi sudah dikirim ke email itu.');
    }
}
