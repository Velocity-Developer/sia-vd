<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Concerns\VerifikasiBuktiBayar;
use App\Http\Controllers\Controller;
use App\Models\TagihanSemester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagihanSemesterController extends Controller
{
    use VerifikasiBuktiBayar;

    /**
     * Unggah (atau ganti) bukti bayar tagihan semester; status menjadi menunggu verifikasi.
     */
    public function unggahBukti(Request $request, TagihanSemester $tagihanSemester): RedirectResponse
    {
        abort_unless($tagihanSemester->mahasiswa_id === $request->user()->mahasiswaProfile?->id, 404);

        $tagihanSemester->diubah_oleh = $request->user()->id;

        return $this->prosesUnggahBukti($request, $tagihanSemester, 'semester', 'bukti-bayar');
    }
}
