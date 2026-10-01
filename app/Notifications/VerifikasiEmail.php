<?php

namespace App\Notifications;

use App\Models\PengaturanInstitusi;
use App\Models\TemplateEmail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * Surel tautan verifikasi alamat email. Isinya dari template yang bisa diubah admin
 * (Pengaturan Sistem > Email), bawaannya berbahasa Indonesia dan memakai nama institusi.
 */
class VerifikasiEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $tautan = $this->verificationUrl($notifiable);

        return TemplateEmail::susun('verifikasi_email', TemplateEmail::isiBerlaku('verifikasi_email'), [
            'nama' => $notifiable->name,
            'username' => $notifiable->username,
            'email' => $notifiable->getEmailForVerification(),
            'institusi' => PengaturanInstitusi::shared()['nama_pt'],
            'menit' => Config::get('auth.verification.expire', 60),
            'tautan' => $tautan,
        ], $tautan);
    }
}
