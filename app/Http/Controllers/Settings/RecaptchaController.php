<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PengaturanRecaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RecaptchaController extends Controller
{
    /**
     * Halaman pengaturan Google reCAPTCHA v2.
     */
    public function edit(): Response
    {
        $pengaturan = PengaturanRecaptcha::current();

        return Inertia::render('PengaturanSistem/Recaptcha', [
            'pengaturan' => [
                'aktif' => $pengaturan->aktif,
                'site_key' => $pengaturan->site_key,
                // Secret key tidak pernah dikirim ke browser, hanya status tersimpan atau belum.
                'secret_key_tersimpan' => filled($pengaturan->secret_key),
            ],
        ]);
    }

    /**
     * Simpan pengaturan reCAPTCHA.
     */
    public function update(Request $request): RedirectResponse
    {
        $pengaturan = PengaturanRecaptcha::current();
        $aktif = $request->boolean('aktif');

        $data = $request->validate([
            'aktif' => ['required', 'boolean'],
            'site_key' => ['required_if:aktif,1,true', 'nullable', 'string', 'max:255'],
            'secret_key' => [$aktif && blank($pengaturan->secret_key) ? 'required' : 'nullable', 'string', 'max:255'],
            'token_uji' => ['nullable', 'string'],
        ], attributes: [
            'aktif' => 'Status captcha',
            'site_key' => 'Site key',
            'secret_key' => 'Secret key',
            'token_uji' => 'Captcha uji',
        ]);

        $secretKey = filled($data['secret_key'] ?? null) ? $data['secret_key'] : $pengaturan->secret_key;
        $kunciBerubah = trim((string) ($data['site_key'] ?? '')) !== (string) $pengaturan->site_key || filled($data['secret_key'] ?? null);

        // Mengaktifkan captcha dengan kunci yang salah membuat semua orang (termasuk admin) tidak bisa
        // masuk, jadi kunci wajib lolos uji di domain ini saat captcha dinyalakan atau kuncinya diganti.
        if ($aktif && (! $pengaturan->aktif || $kunciBerubah) && ! PengaturanRecaptcha::verifikasi($secretKey, $data['token_uji'] ?? null, $request->ip())) {
            throw ValidationException::withMessages([
                'token_uji' => filled($data['token_uji'] ?? null)
                    ? 'Captcha uji ditolak Google. Pastikan site key dan secret key berpasangan dan domain ini terdaftar di konsol reCAPTCHA.'
                    : 'Centang captcha uji di bawah sebelum mengaktifkan captcha.',
            ]);
        }

        unset($data['token_uji']);

        // Secret key dibiarkan apa adanya bila kolomnya dikosongkan, karena isinya tidak
        // pernah ditampilkan kembali di halaman.
        if (blank($data['secret_key'] ?? null)) {
            unset($data['secret_key']);
        }

        $data['site_key'] = filled($data['site_key'] ?? null) ? trim($data['site_key']) : null;
        $data['updated_by'] = $request->user()->id;
        $pengaturan->update($data);

        return back()->with('success', 'Pengaturan reCAPTCHA berhasil disimpan.');
    }
}
