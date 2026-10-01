<?php

namespace App\Notifications;

use App\Models\PengaturanInstitusi;
use App\Models\TemplateEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Surel tautan atur ulang kata sandi. Isinya dari template yang bisa diubah admin
 * (Pengaturan Sistem > Email), bawaannya berbahasa Indonesia dan memakai nama institusi.
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
        $tautan = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));

        return TemplateEmail::susun('atur_ulang_kata_sandi', TemplateEmail::isiBerlaku('atur_ulang_kata_sandi'), [
            'nama' => $notifiable->name,
            'username' => $notifiable->username,
            'email' => $notifiable->getEmailForPasswordReset(),
            'institusi' => PengaturanInstitusi::shared()['nama_pt'],
            'menit' => Config::get('auth.passwords.'.Config::get('auth.defaults.passwords').'.expire', 60),
            'tautan' => $tautan,
        ], $tautan);
    }
}
