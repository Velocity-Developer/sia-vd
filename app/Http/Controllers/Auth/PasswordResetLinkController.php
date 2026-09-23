<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
        ], attributes: ['login' => 'Username atau email']);

        // Pengguna masuk memakai username (NIM/NIDN), banyak yang tidak hafal surelnya,
        // jadi keduanya diterima lalu tautan tetap dikirim ke surel terdaftar.
        $user = User::query()
            ->where('username', $data['login'])
            ->orWhere('email', $data['login'])
            ->first();

        if ($user?->email) {
            Password::sendResetLink(['email' => $user->email]);
        }

        // Jawaban selalu sama agar tidak bisa dipakai menebak akun yang terdaftar.
        return back()->with('status', 'Jika akun tersebut terdaftar, tautan atur ulang kata sandi sudah dikirim ke surelnya.');
    }
}
