<?php

namespace App\Http\Controllers\Kelas;

use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\RemidiPeserta;
use App\UsulanRemidi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($kelasKuliah, $daftar, $dipilih, $request): void {
            RemidiPeserta::query()->where('kelas_id', $kelasKuliah->id)->delete();
            RemidiPeserta::query()->insert($dipilih->map(fn (int $id): array => [
                'kelas_id' => $kelasKuliah->id,
                'mahasiswa_id' => $id,
                'nilai_awal' => $daftar[$id]['nilai'],
                'diusulkan' => $daftar[$id]['diusulkan'],
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
            $kelasKuliah->update(['remidi_dikunci_at' => now(), 'remidi_dikunci_oleh' => $request->user()->id]);
        });

        return back()->with('success', $dipilih->isEmpty()
            ? 'Daftar remidi dikunci tanpa peserta.'
            : "Daftar remidi dikunci dengan {$dipilih->count()} peserta.");
    }

    /**
     * Admin membuka kunci daftar agar dosen bisa mengubahnya lagi. Pilihan yang tersimpan tetap dipakai.
     */
    public function buka(KelasKuliah $kelasKuliah): RedirectResponse
    {
        $kelasKuliah->update(['remidi_dikunci_at' => null, 'remidi_dikunci_oleh' => null]);

        return back()->with('success', 'Kunci daftar remidi dibuka.');
    }
}
