<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if (! $request->user()->kirimVerifikasiEmail()) {
            return back()->with('error', 'Surel verifikasi gagal dikirim. Coba lagi beberapa saat lagi atau hubungi admin.');
        }

        return back()->with('status', 'verification-link-sent');
    }
}
