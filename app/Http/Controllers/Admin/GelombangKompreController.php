<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GelombangKompre;
use App\Models\PengajuanAkademik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gelombang ujian komprehensif (Pengajuan & Pendaftaran → Ujian Komprehensif). Mahasiswa memilih gelombang yang dibuka
 * saat mengajukan ujian komprehensif; pendaftarnya diproses di Daftar Ujian Komprehensif.
 */
class GelombangKompreController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/GelombangKompre', [
            'gelombang' => GelombangKompre::query()->orderByDesc('tanggal_ujian')->get()
                ->map(fn (GelombangKompre $g): array => [
                    ...$g->ringkas(),
                    'dibuka' => $g->dibuka(),
                    'jumlah_pendaftar' => $g->pendaftar()->count(),
                    'jumlah_disetujui' => $g->pendaftar()->where('status', PengajuanAkademik::DISETUJUI)->count(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        GelombangKompre::query()->create($this->validasi($request));

        return back()->with('success', 'Gelombang ujian komprehensif dibuat.');
    }

    public function update(Request $request, GelombangKompre $gelombangKompre): RedirectResponse
    {
        $data = $this->validasi($request);
        if ($data['kuota'] !== null && $data['kuota'] < $gelombangKompre->pendaftar()->count()) {
            return back()->withErrors(['kuota' => 'Kuota tidak boleh lebih kecil dari jumlah pendaftar gelombang ini.']);
        }
        $gelombangKompre->update($data);

        return back()->with('success', 'Gelombang ujian komprehensif diperbarui.');
    }

    public function destroy(GelombangKompre $gelombangKompre): RedirectResponse
    {
        if ($gelombangKompre->pendaftar()->exists()) {
            return back()->with('error', 'Gelombang ini sudah punya pendaftar sehingga tidak bisa dihapus.');
        }
        $gelombangKompre->delete();

        return back()->with('success', 'Gelombang ujian komprehensif dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'tanggal_buka' => ['required', 'date_format:Y-m-d'],
            'tanggal_tutup' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_buka'],
            'tanggal_ujian' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_tutup'],
            'kuota' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ], [
            'tanggal_tutup.after_or_equal' => 'Tanggal tutup tidak boleh sebelum tanggal buka.',
            'tanggal_ujian.after_or_equal' => 'Tanggal ujian tidak boleh sebelum pendaftaran ditutup.',
        ], [
            'nama' => 'Nama gelombang',
            'tanggal_buka' => 'Tanggal buka',
            'tanggal_tutup' => 'Tanggal tutup',
            'tanggal_ujian' => 'Tanggal ujian',
            'kuota' => 'Kuota',
            'keterangan' => 'Keterangan',
        ]) + ['kuota' => null, 'keterangan' => null];
    }
}
