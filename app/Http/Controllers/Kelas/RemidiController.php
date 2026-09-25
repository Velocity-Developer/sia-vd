<?php

namespace App\Http\Controllers\Kelas;

use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\TagihanRemidi;
use App\UsulanRemidi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Daftar peserta remidi per kelas: disusun dosen (atau admin) setelah nilai kelas final, lalu dikunci.
 */
class RemidiController extends Controller
{
    use KontenKelas;

    public function kunci(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $this->pastikanAksesKelas($kelasKuliah);

        if ($this->tahunAkademikTerkunci($kelasKuliah)) {
            return back()->with('error', 'Tahun akademik kelas ini sudah tidak aktif. Hubungi admin.');
        }

        if (! $kelasKuliah->nilaiFinal()) {
            return back()->with('error', 'Daftar remidi baru bisa disusun setelah nilai kelas difinalisasi.');
        }

        if ($kelasKuliah->remidi_dikunci_at !== null) {
            return back()->with('error', 'Daftar remidi kelas ini sudah dikunci.');
        }

        $validated = $request->validate([
            'mahasiswa_ids' => ['present', 'array'],
            'mahasiswa_ids.*' => ['integer', 'distinct'],
        ]);
        $daftar = collect(UsulanRemidi::susun($kelasKuliah)['mahasiswa'])->keyBy('mahasiswa_id');
        $dipilih = collect($validated['mahasiswa_ids'])->map(fn ($id): int => (int) $id);

        abort_if($dipilih->contains(fn (int $id): bool => ! $daftar->has($id)), 422, 'Mahasiswa bukan peserta kelas ini.');

        UsulanRemidi::kunci($kelasKuliah, $dipilih, $daftar, $request->user());

        return back()->with('success', $dipilih->isEmpty()
            ? 'Daftar remidi dikunci tanpa peserta.'
            : "Daftar remidi dikunci dengan {$dipilih->count()} peserta.");
    }

    /**
     * Admin membuka kunci daftar agar dosen bisa mengubahnya lagi. Pilihan yang tersimpan tetap dipakai.
     */
    public function buka(KelasKuliah $kelasKuliah): RedirectResponse
    {
        if (TagihanRemidi::query()->where('kelas_id', $kelasKuliah->id)->exists()) {
            return back()->with('error', 'Tagihan remidi kelas ini sudah terbit, daftar tidak bisa dibuka lagi.');
        }

        $kelasKuliah->update(['remidi_dikunci_at' => null, 'remidi_dikunci_oleh' => null]);

        return back()->with('success', 'Kunci daftar remidi dibuka.');
    }

    /**
     * Dosen menutup remidi: nilai remidi dan huruf akhir peserta terkunci lagi sebelum batas input nilai remidi.
     */
    public function finalisasi(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $this->pastikanAksesKelas($kelasKuliah);
        $ujian = $kelasKuliah->ujianRemidi();

        if ($ujian === null || ! $ujian->sudahSelesai()) {
            return back()->with('error', 'Remidi baru bisa difinalisasi setelah ujian remidi selesai.');
        }

        if (! $kelasKuliah->jendelaRemidiTerbuka()) {
            return back()->with('error', 'Remidi kelas ini sudah terkunci.');
        }

        $kelasKuliah->update(['remidi_final_at' => now()]);

        return back()->with('success', 'Remidi difinalisasi. Nilai remidi dan huruf akhir peserta kini terkunci.');
    }

    public function bukaFinalisasi(KelasKuliah $kelasKuliah): RedirectResponse
    {
        $kelasKuliah->update(['remidi_final_at' => null]);

        return back()->with('success', 'Finalisasi remidi dibuka. Dosen bisa mengubah nilai remidi sampai batas input nilai remidi.');
    }
}
