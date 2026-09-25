<?php

namespace App\Http\Controllers\Mahasiswa;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\Models\TagihanRemidi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TagihanRemidiController extends Controller
{
    /**
     * Unggah (atau ganti) bukti bayar tagihan remidi sebelum batas bayar; status menjadi menunggu verifikasi.
     */
    public function unggahBukti(Request $request, TagihanRemidi $tagihanRemidi): RedirectResponse
    {
        abort_unless($tagihanRemidi->mahasiswa_id === $request->user()->mahasiswaProfile?->id, 404);

        $ekstensi = implode(',', TagihanRemidi::EKSTENSI_BUKTI);
        $request->validate([
            'bukti' => ['required', 'file', 'max:5120', 'extensions:'.$ekstensi, 'mimes:'.$ekstensi],
        ], [
            'bukti.extensions' => 'Bukti bayar harus berupa PDF atau foto (JPG/PNG).',
            'bukti.mimes' => 'Isi berkas tidak sesuai dengan formatnya.',
            'bukti.max' => 'Ukuran bukti bayar maksimal 5 MB.',
        ], ['bukti' => 'Bukti bayar']);

        if (! $tagihanRemidi->bolehUnggah()) {
            return back()->with('error', $tagihanRemidi->status === TagihanRemidi::LUNAS
                ? 'Tagihan ini sudah lunas.'
                : 'Batas bayar remidi sudah lewat.');
        }

        $berkas = $request->file('bukti');
        $path = $berkas->storeAs('bukti-bayar', Str::random(24).'.'.strtolower($berkas->getClientOriginalExtension()), AllowedUpload::DISK);
        $lama = $tagihanRemidi->bukti;

        $tagihanRemidi->update([
            'bukti' => $path,
            'bukti_diunggah_at' => now(),
            'status' => TagihanRemidi::MENUNGGU,
            'alasan_tolak' => null,
            'diverifikasi_oleh' => null,
            'diverifikasi_at' => null,
        ]);

        if ($lama !== null) {
            Storage::disk(AllowedUpload::DISK)->delete($lama);
        }

        return back()->with('success', 'Bukti bayar terkirim dan menunggu verifikasi admin.');
    }
}
