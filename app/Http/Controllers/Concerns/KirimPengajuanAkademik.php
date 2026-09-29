<?php

namespace App\Http\Controllers\Concerns;

use App\AllowedUpload;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanAkademik;
use App\Models\TugasAkhir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kirim pengajuan akademik dari form mahasiswa (TA, pendadaran, wisuda, cuti, aktif kembali).
 * Kelas pemakai mendefinisikan konstanta PERBAIKAN untuk keadaan "perlu perbaikan".
 */
trait KirimPengajuanAkademik
{
    /**
     * Alur kirim bersama: kunci baris mahasiswa, tentukan keadaan form, validasi, simpan berkas, lalu kirim.
     *
     * @param  callable(?PengajuanAkademik): string  $keadaan
     * @param  callable(bool): array<string, list<string>>  $aturan  menerima "sedang perbaikan"
     * @param  array<string, string>  $pesan
     * @param  array<string, string>  $atribut
     * @param  array<string, string>  $pesanKeadaan  keadaan yang menolak kiriman => pesan
     */
    private function simpan(Request $request, MahasiswaProfile $mahasiswa, string $jenis, callable $keadaan, callable $aturan, array $pesan, array $atribut, array $pesanKeadaan, string $nama, string $kunciGalat = 'judul'): RedirectResponse
    {
        return DB::transaction(function () use ($request, $mahasiswa, $jenis, $keadaan, $aturan, $pesan, $atribut, $pesanKeadaan, $nama, $kunciGalat): RedirectResponse {
            // Kunci baris mahasiswa agar dua kiriman bersamaan tidak membuat dua pengajuan.
            MahasiswaProfile::query()->whereKey($mahasiswa->id)->lockForUpdate()->first();

            $pengajuan = PengajuanAkademik::terakhir($mahasiswa->id, $jenis);
            $status = $keadaan($pengajuan);
            if (isset($pesanKeadaan[$status])) {
                throw ValidationException::withMessages([$kunciGalat => $pesanKeadaan[$status]]);
            }
            $perbaikan = $status === self::PERBAIKAN;

            $aturanBerkas = $aturan($perbaikan);
            $data = $request->validate($aturanBerkas, [...$pesan, '*.mimes' => 'Isi berkas :attribute tidak sesuai dengan formatnya.'], $atribut);

            $kunciBerkas = collect($aturanBerkas)->filter(fn (array $r): bool => in_array('file', $r, true))->keys();
            $lampiran = $perbaikan ? $pengajuan->lampiran : [];
            foreach ($kunciBerkas as $kunci) {
                $berkas = $request->file($kunci);
                if (! $berkas instanceof UploadedFile) {
                    continue;
                }
                if (isset($lampiran[$kunci])) {
                    Storage::disk(AllowedUpload::DISK)->delete($lampiran[$kunci]);
                }
                $lampiran[$kunci] = $berkas->storeAs('pengajuan-akademik', Str::random(24).'.'.strtolower($berkas->getClientOriginalExtension()), AllowedUpload::DISK);
            }

            $isian = collect($data)->except($kunciBerkas->all())->all();
            if ($perbaikan) {
                $pengajuan->update(['isian' => $isian, 'lampiran' => $lampiran]);
            } else {
                $pengajuan = PengajuanAkademik::query()->create([
                    'mahasiswa_id' => $mahasiswa->id,
                    'jenis' => $jenis,
                    'tugas_akhir_id' => in_array($jenis, [PengajuanAkademik::PENDADARAN, PengajuanAkademik::WISUDA], true) ? TugasAkhir::milik($mahasiswa->id)?->id : null,
                    'isian' => $isian,
                    'lampiran' => $lampiran,
                ]);
            }
            $pengajuan->kirim($request->user()->id);

            $tujuan = $pengajuan->status === PengajuanAkademik::MENUNGGU_PEMBIMBING ? 'pembimbing' : 'admin';

            return back()->with('success', ($perbaikan ? 'Perbaikan ' : 'Pengajuan ').$nama.' terkirim dan menunggu diproses '.$tujuan.'.');
        });
    }
}
