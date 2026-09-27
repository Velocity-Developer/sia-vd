<?php

namespace App\Http\Controllers;

use App\AllowedUpload;
use App\Models\Pendadaran;
use App\Models\PengaturanInstitusi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Surat pendadaran (PDF) dan naskah revisi, untuk mahasiswa, admin, pembimbing, dan penguji.
 */
class PendadaranBerkasController extends Controller
{
    public function surat(Request $request, Pendadaran $pendadaran): Response
    {
        abort_unless($pendadaran->bolehDilihat($request->user()), 403);

        $pendadaran->load(['mahasiswa.user:id,name', 'mahasiswa.prodi', 'tugasAkhir.pembimbing1.user:id,name', 'tugasAkhir.pembimbing2.user:id,name', 'ruang', 'penguji1.user:id,name', 'penguji2.user:id,name', 'penguji3.user:id,name']);
        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView('pdf.surat-pendadaran', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'pendadaran' => $pendadaran,
        ])->stream('surat-pendadaran-'.$pendadaran->mahasiswa?->nim.'.pdf');
    }

    public function naskahRevisi(Request $request, Pendadaran $pendadaran): StreamedResponse
    {
        abort_unless($pendadaran->bolehDilihat($request->user()), 403);

        $disk = Storage::disk(AllowedUpload::DISK);
        abort_if($pendadaran->naskah_revisi === null || ! $disk->exists($pendadaran->naskah_revisi), 404);

        return $disk->response($pendadaran->naskah_revisi, 'naskah-revisi-'.$pendadaran->mahasiswa?->nim.'.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ], 'inline');
    }
}
