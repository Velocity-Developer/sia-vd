<?php

namespace App\Mail;

use App\Models\PengaturanInstitusi;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Surel uji dari halaman Pengaturan Email untuk memastikan konfigurasi pengiriman benar.
 */
class SurelUji extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Uji Pengiriman Email — '.PengaturanInstitusi::shared()['nama_pt']);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.uji', with: [
            'institusi' => PengaturanInstitusi::shared()['nama_pt'],
        ]);
    }
}
