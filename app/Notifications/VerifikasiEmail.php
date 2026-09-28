<?php

namespace App\Notifications;

use App\Models\PengaturanInstitusi;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * Surel tautan verifikasi alamat email, berbahasa Indonesia dan memakai nama institusi.
 */
class VerifikasiEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $institusi = PengaturanInstitusi::shared()['nama_pt'];
        $menit = Config::get('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Verifikasi Alamat Email — '.$institusi)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Alamat email ini didaftarkan untuk akun '.$notifiable->username.' di '.$institusi.'.')
            ->line('Klik tombol di bawah untuk memverifikasi alamat email Anda.')
            ->action('Verifikasi Email', $this->verificationUrl($notifiable))
            ->line('Tautan ini berlaku '.$menit.' menit. Bila sudah kedaluwarsa, masuk ke aplikasi lalu minta tautan baru.')
            ->line('Jika Anda tidak merasa memiliki akun ini, abaikan surel ini.')
            ->salutation('Salam, '.$institusi);
    }
}
