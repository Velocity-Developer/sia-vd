<?php

namespace App\Notifications;

use App\Models\PengaturanInstitusi;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;

/**
 * Surel tautan atur ulang kata sandi. Dibuat sendiri agar isinya berbahasa Indonesia
 * dan memakai nama institusi yang dipakai di seluruh sistem.
 */
class AturUlangKataSandi extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $institusi = PengaturanInstitusi::shared()['nama_pt'];
        $menit = Config::get('auth.passwords.'.Config::get('auth.defaults.passwords').'.expire', 60);
        $tautan = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi — '.$institusi)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun '.$notifiable->username.' di '.$institusi.'.')
            ->action('Atur Ulang Kata Sandi', url($tautan))
            ->line('Tautan ini berlaku '.$menit.' menit.')
            ->line('Jika Anda tidak merasa meminta, abaikan surel ini; kata sandi Anda tidak berubah.')
            ->salutation(Lang::get('Salam, ').$institusi);
    }
}
