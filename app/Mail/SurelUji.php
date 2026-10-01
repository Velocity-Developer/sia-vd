<?php

namespace App\Mail;

use App\Models\PengaturanInstitusi;
use App\Models\TemplateEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * Surel uji dari halaman Pengaturan Email untuk memastikan konfigurasi pengiriman benar.
 * Isinya dari template "uji" yang bisa diubah admin.
 */
class SurelUji extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $emailTujuan = '') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->pesan()->subject);
    }

    public function content(): Content
    {
        $pesan = $this->pesan();

        return new Content(markdown: $pesan->markdown, with: $pesan->viewData);
    }

    private function pesan(): MailMessage
    {
        return TemplateEmail::susun('uji', TemplateEmail::isiBerlaku('uji'), [
            'institusi' => PengaturanInstitusi::shared()['nama_pt'],
            'email' => $this->emailTujuan,
        ]);
    }
}
