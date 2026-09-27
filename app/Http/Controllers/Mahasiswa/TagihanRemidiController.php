<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Concerns\VerifikasiBuktiBayar;
use App\Http\Controllers\Controller;
use App\Models\TagihanRemidi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagihanRemidiController extends Controller
{
    use VerifikasiBuktiBayar;

    /**
     * Unggah (atau ganti) bukti bayar tagihan remidi sebelum batas bayar; status menjadi menunggu verifikasi.
     */
    public function unggahBukti(Request $request, TagihanRemidi $tagihanRemidi): RedirectResponse
    {
        abort_unless($tagihanRemidi->mahasiswa_id === $request->user()->mahasiswaProfile?->id, 404);

        return $this->prosesUnggahBukti($request, $tagihanRemidi, 'remidi', 'bukti-bayar');
    }
}
