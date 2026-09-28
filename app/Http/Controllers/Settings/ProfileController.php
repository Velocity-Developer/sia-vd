<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\UserType;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'namaTerkunci' => $request->user()->type() === UserType::Mahasiswa,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        $emailBerubah = $request->user()->isDirty('email');
        if ($emailBerubah) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($emailBerubah && ! $request->user()->kirimVerifikasiEmail()) {
            return to_route('profile.edit')->with('error', 'Profil tersimpan, tetapi surel verifikasi gagal dikirim. Coba kirim ulang beberapa saat lagi.');
        }

        return to_route('profile.edit')->with('status', $emailBerubah ? 'verification-link-sent' : null);
    }
}
