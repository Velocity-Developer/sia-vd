<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InfoBiayaKuliahController extends Controller
{
    /**
     * Tagihan semester berjalan beserta riwayat semester sebelumnya milik mahasiswa yang masuk.
     */
    public function index(Request $request): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;

        $tagihan = TagihanSemester::query()
            ->with(['items', 'tahunAkademik'])
            ->where('mahasiswa_id', $mahasiswa?->id)
            ->get()
            ->sortByDesc(fn (TagihanSemester $item) => [$item->tahunAkademik?->tahun, $item->tahunAkademik?->semester])
            ->values();

        $tahunAktif = TahunAkademik::query()->where('status', true)->first();
        $berjalan = $tagihan->firstWhere('tahun_akademik_id', $tahunAktif?->id);

        return Inertia::render('Mahasiswa/InfoBiayaKuliah', [
            'semesterBerjalan' => $berjalan ? $this->bentuk($berjalan) : null,
            'tahunAktif' => $tahunAktif ? $tahunAktif->tahun.' '.$tahunAktif->semester : null,
            'riwayat' => $tagihan
                ->filter(fn (TagihanSemester $item) => $item->tahun_akademik_id !== $tahunAktif?->id)
                ->map(fn (TagihanSemester $item) => $this->bentuk($item))
                ->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bentuk(TagihanSemester $tagihan): array
    {
        return [
            'id' => $tagihan->id,
            'tahun_akademik' => $tagihan->tahunAkademik ? $tagihan->tahunAkademik->tahun.' '.$tagihan->tahunAkademik->semester : '-',
            'status' => $tagihan->status,
            'total' => $tagihan->total,
            'tanggal_lunas' => $tagihan->tanggal_lunas?->toDateString(),
            'items' => $tagihan->items->map(fn ($item): array => [
                'nama' => $item->nama,
                'cara_hitung' => $item->cara_hitung,
                'nominal_satuan' => $item->nominal_satuan,
                'jumlah' => $item->jumlah,
                'subtotal' => $item->subtotal,
            ])->all(),
        ];
    }
}
