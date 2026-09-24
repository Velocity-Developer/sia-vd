<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\SurelUji;
use App\Models\PengaturanEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class EmailController extends Controller
{
    /**
     * Halaman pengaturan pengiriman surel.
     */
    public function edit(): Response
    {
        $pengaturan = PengaturanEmail::current();

        return Inertia::render('PengaturanSistem/Email', [
            'pengaturan' => [
                'mailer' => $pengaturan->mailer,
                'host' => $pengaturan->host,
                'port' => $pengaturan->port,
                'encryption' => $pengaturan->encryption ?? 'tls',
                'username' => $pengaturan->username,
                'from_address' => $pengaturan->from_address,
                'from_name' => $pengaturan->from_name,
                // Kata sandi SMTP tidak pernah dikirim ke browser, hanya status tersimpan atau belum.
                'password_tersimpan' => filled($pengaturan->password),
            ],
        ]);
    }

    /**
     * Simpan pengaturan pengiriman surel.
     */
    public function update(Request $request): RedirectResponse
    {
        $smtp = $request->input('mailer') === 'smtp';

        $data = $request->validate([
            'mailer' => ['required', Rule::in(['log', 'smtp'])],
            'host' => [Rule::requiredIf($smtp), 'nullable', 'string', 'max:255'],
            'port' => [Rule::requiredIf($smtp), 'nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => [Rule::requiredIf($smtp), 'nullable', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => [Rule::requiredIf($smtp), 'nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'mailer' => 'Metode pengiriman',
            'host' => 'Host SMTP',
            'port' => 'Port',
            'encryption' => 'Enkripsi',
            'username' => 'Username SMTP',
            'password' => 'Kata sandi SMTP',
            'from_address' => 'Email pengirim',
            'from_name' => 'Nama pengirim',
        ]);

        $pengaturan = PengaturanEmail::current();

        // Kata sandi dibiarkan apa adanya bila kolomnya dikosongkan, karena isinya tidak
        // pernah ditampilkan kembali di halaman.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $data['updated_by'] = $request->user()->id;
        $pengaturan->update($data);

        return back()->with('success', 'Pengaturan email berhasil disimpan.');
    }

    /**
     * Kirim surel uji memakai pengaturan yang tersimpan.
     */
    public function uji(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_tujuan' => ['required', 'email', 'max:255'],
        ], attributes: ['email_tujuan' => 'Email tujuan']);

        PengaturanEmail::terapkan();

        try {
            Mail::to($data['email_tujuan'])->send(new SurelUji);
        } catch (Throwable $e) {
            return back()->with('error', 'Surel uji gagal dikirim: '.$e->getMessage());
        }

        return back()->with('success', 'Surel uji dikirim ke '.$data['email_tujuan'].'.');
    }
}
