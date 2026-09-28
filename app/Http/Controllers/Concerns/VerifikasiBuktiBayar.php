<?php

namespace App\Http\Controllers\Concerns;

use App\AllowedUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Alur bayar lewat unggah bukti untuk model yang memakai trait TagihanBerbukti:
 * mahasiswa mengunggah atau mengganti bukti, admin menandai lunas atau menolak dengan alasan.
 */
trait VerifikasiBuktiBayar
{
    /**
     * Simpan (atau ganti) bukti bayar; status menjadi menunggu verifikasi. Pemanggil memastikan tagihan milik mahasiswa itu.
     */
    protected function prosesUnggahBukti(Request $request, Model $tagihan, string $jenis, string $direktori, ?string $larangan = null): RedirectResponse
    {
        $ekstensi = implode(',', $tagihan::EKSTENSI_BUKTI);
        $request->validate([
            'bukti' => ['required', 'file', 'max:5120', 'extensions:'.$ekstensi, 'mimes:'.$ekstensi],
        ], [
            'bukti.extensions' => 'Bukti bayar harus berupa PDF atau foto (JPG/PNG).',
            'bukti.mimes' => 'Isi berkas tidak sesuai dengan formatnya.',
            'bukti.max' => 'Ukuran bukti bayar maksimal 5 MB.',
        ], ['bukti' => 'Bukti bayar']);

        if ($larangan !== null) {
            return back()->with('error', $larangan);
        }

        if (! $tagihan->bolehUnggah()) {
            return back()->with('error', $tagihan->status === $tagihan::LUNAS ? 'Tagihan ini sudah lunas.' : "Batas bayar {$jenis} sudah lewat.");
        }

        $berkas = $request->file('bukti');
        $path = $berkas->storeAs($direktori, Str::random(24).'.'.strtolower($berkas->getClientOriginalExtension()), AllowedUpload::DISK);
        $lama = $tagihan->bukti;

        $tagihan->update([
            'bukti' => $path,
            'bukti_diunggah_at' => now(),
            'status' => $tagihan::MENUNGGU,
            'alasan_tolak' => null,
            'diverifikasi_oleh' => null,
            'diverifikasi_at' => null,
        ]);

        if ($lama !== null) {
            Storage::disk(AllowedUpload::DISK)->delete($lama);
        }

        return back()->with('success', 'Bukti bayar terkirim dan menunggu verifikasi admin.');
    }

    /**
     * Tandai lunas, dengan atau tanpa bukti (mis. dibayar di loket).
     */
    protected function prosesTandaiLunas(Request $request, Model $tagihan, string $pesan, ?string $larangan = null): RedirectResponse
    {
        if ($tagihan->status === $tagihan::LUNAS) {
            return back()->with('error', 'Tagihan ini sudah lunas.');
        }

        if ($larangan !== null) {
            return back()->with('error', $larangan);
        }

        $tagihan->update([
            'status' => $tagihan::LUNAS,
            'alasan_tolak' => null,
            'diverifikasi_oleh' => $request->user()->id,
            'diverifikasi_at' => now(),
        ]);

        return back()->with('success', $pesan);
    }

    protected function prosesTolakBukti(Request $request, Model $tagihan): RedirectResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']], attributes: ['alasan' => 'Alasan']);

        if ($tagihan->status !== $tagihan::MENUNGGU) {
            return back()->with('error', 'Hanya bukti yang menunggu verifikasi yang bisa ditolak.');
        }

        $tagihan->update([
            'status' => $tagihan::DITOLAK,
            'alasan_tolak' => $data['alasan'],
            'diverifikasi_oleh' => $request->user()->id,
            'diverifikasi_at' => now(),
        ]);

        return back()->with('success', 'Bukti bayar ditolak. Mahasiswa bisa mengunggah ulang'.($tagihan->batasBayar() !== null ? ' sebelum batas bayar.' : '.'));
    }
}
