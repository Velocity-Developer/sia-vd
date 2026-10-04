<?php

namespace App\Http\Controllers;

use App\AllowedUpload;
use App\Models\Pendadaran;
use App\Models\PengaturanInstitusi;
use App\Models\TugasAkhir;
use App\Models\Wisuda;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Surat pendadaran (PDF) dan naskah revisi untuk mahasiswa, admin, pembimbing, dan penguji; SKL untuk
 * mahasiswa pemiliknya dan admin; naskah TA untuk mahasiswa pemiliknya, admin, dan pembimbingnya.
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

    public function skl(Request $request, Wisuda $wisuda): Response
    {
        $user = $request->user();
        abort_unless($user->mahasiswaProfile?->id === $wisuda->mahasiswa_id || $user->hasPermission('admin.pengajuan-akademik'), 403);
        abort_if($wisuda->nomor_skl === null, 404);

        $wisuda->load(['mahasiswa.user:id,name', 'mahasiswa.prodi.fakultas', 'tugasAkhir', 'pengajuan:id,isian']);
        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView('pdf.skl', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'wisuda' => $wisuda,
            'isian' => $wisuda->pengajuan?->isian ?? [],
        ])->stream('skl-'.$wisuda->mahasiswa?->nim.'.pdf');
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

    public function naskahTa(Request $request, TugasAkhir $tugasAkhir): StreamedResponse
    {
        $user = $request->user();
        $dosenId = $user->dosenProfile?->id;
        abort_unless($user->mahasiswaProfile?->id === $tugasAkhir->mahasiswa_id || $user->hasPermission('admin.pengajuan-akademik')
            || ($dosenId !== null && $tugasAkhir->dibimbingOleh($dosenId)), 403);

        $disk = Storage::disk(AllowedUpload::DISK);
        abort_if($tugasAkhir->naskah === null || ! $disk->exists($tugasAkhir->naskah), 404);

        return $disk->response($tugasAkhir->naskah, 'naskah-ta-'.$tugasAkhir->mahasiswa?->nim.'.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ], 'inline');
    }
}
