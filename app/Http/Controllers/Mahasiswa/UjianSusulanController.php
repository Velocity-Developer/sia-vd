<?php

namespace App\Http\Controllers\Mahasiswa;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\Models\PengajuanSusulan;
use App\Models\Ujian;
use App\UjianSusulan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UjianSusulanController extends Controller
{
    /**
     * Ajukan ujian susulan untuk UTS/UAS yang tidak (atau tidak akan) diikuti, dengan alasan dan bukti.
     */
    public function ajukan(Request $request, Ujian $ujian): RedirectResponse
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        $ekstensi = implode(',', PengajuanSusulan::EKSTENSI_LAMPIRAN);
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:1000'],
            'lampiran' => ['required', 'array', 'min:1', 'max:3'],
            'lampiran.*' => ['file', 'max:5120', 'extensions:'.$ekstensi, 'mimes:'.$ekstensi],
        ], [
            'lampiran.required' => 'Lampirkan bukti alasan (mis. surat keterangan).',
            'lampiran.*.extensions' => 'Lampiran harus berupa PDF atau foto (JPG/PNG).',
            'lampiran.*.mimes' => 'Isi lampiran tidak sesuai dengan formatnya.',
            'lampiran.*.max' => 'Ukuran lampiran maksimal 5 MB.',
        ], ['alasan' => 'Alasan', 'lampiran' => 'Lampiran']);

        if (($alasan = UjianSusulan::alasanTidakBolehAjukan($ujian, $mahasiswa)) !== null) {
            throw ValidationException::withMessages(['alasan' => $alasan]);
        }

        $lampiran = collect($request->file('lampiran'))
            ->map(fn ($berkas): string => $berkas->storeAs('susulan', Str::random(24).'.'.strtolower($berkas->getClientOriginalExtension()), AllowedUpload::DISK))
            ->all();

        PengajuanSusulan::query()->create([
            'ujian_id' => $ujian->id,
            'mahasiswa_id' => $mahasiswa->id,
            'alasan' => $data['alasan'],
            'lampiran' => $lampiran,
            'status' => PengajuanSusulan::MENUNGGU,
        ]);

        return back()->with('success', 'Pengajuan ujian susulan terkirim dan menunggu persetujuan admin.');
    }

    /**
     * Batalkan pengajuan sendiri selama belum diproses admin.
     */
    public function batalkan(Request $request, PengajuanSusulan $pengajuanSusulan): RedirectResponse
    {
        abort_unless($pengajuanSusulan->mahasiswa_id === $request->user()->mahasiswaProfile?->id, 404);

        if ($pengajuanSusulan->status !== PengajuanSusulan::MENUNGGU) {
            return back()->with('error', 'Pengajuan yang sudah diproses tidak bisa dibatalkan.');
        }

        $pengajuanSusulan->update(['status' => PengajuanSusulan::DIBATALKAN]);
        Storage::disk(AllowedUpload::DISK)->delete($pengajuanSusulan->lampiran ?? []);
        $pengajuanSusulan->update(['lampiran' => []]);

        return back()->with('success', 'Pengajuan ujian susulan dibatalkan.');
    }
}
