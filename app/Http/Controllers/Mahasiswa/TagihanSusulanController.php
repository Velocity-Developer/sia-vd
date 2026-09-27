<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Concerns\VerifikasiBuktiBayar;
use App\Http\Controllers\Controller;
use App\Models\TagihanSusulan;
use App\UjianSusulan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagihanSusulanController extends Controller
{
    use VerifikasiBuktiBayar;

    /**
     * Unggah (atau ganti) bukti bayar tagihan susulan sebelum batas bayar; status menjadi menunggu verifikasi.
     */
    public function unggahBukti(Request $request, TagihanSusulan $tagihanSusulan): RedirectResponse
    {
        abort_unless($tagihanSusulan->mahasiswa_id === $request->user()->mahasiswaProfile?->id, 404);

        $larangan = UjianSusulan::ikutUjianUtama($tagihanSusulan->ujian, $tagihanSusulan->mahasiswa_id)
            ? 'Anda sudah mengikuti ujian utama, jadi tagihan susulan ini dibatalkan.'
            : null;

        return $this->prosesUnggahBukti($request, $tagihanSusulan, 'susulan', 'bukti-bayar', $larangan);
    }
}
