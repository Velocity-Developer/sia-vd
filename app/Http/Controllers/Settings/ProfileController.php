<?php

namespace App\Http\Controllers\Settings;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\UserType;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            // Akun tanpa data profil akademik (mis. Developer) tidak punya tempat menyimpan foto.
            'bisaUbahFoto' => $request->user()->folderFoto() !== null && $request->user()->profile()->exists(),
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

    /**
     * Ganti atau hapus foto profil sendiri. Berkas lama dihapus sesudah profil tersimpan.
     */
    public function updateFoto(Request $request): RedirectResponse
    {
        $request->validate([
            'foto' => ['nullable', 'required_without:hapus_foto', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'hapus_foto' => ['nullable', 'boolean'],
        ], ['foto.required_without' => 'Pilih foto terlebih dahulu.'], ['foto' => 'foto']);

        $user = $request->user();
        $folder = $user->folderFoto();
        $profil = $folder === null ? null : $user->profile()->first();
        abort_if($profil === null, 403);

        $lama = $profil->foto;
        $baru = $request->hasFile('foto') ? $request->file('foto')->store($folder, AllowedUpload::DISK) : null;
        $profil->forceFill(['foto' => $baru])->save();

        if ($lama !== null) {
            Storage::disk(AllowedUpload::DISK)->delete($lama);
        }

        return to_route('profile.edit')->with('success', $baru ? 'Foto profil berhasil diperbarui.' : 'Foto profil dihapus.');
    }
}
