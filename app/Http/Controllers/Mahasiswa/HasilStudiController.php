<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Krs;
use App\Models\TahunAkademik;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HasilStudiController extends Controller
{
    public function index(Request $request): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        $tahunAkademik = TahunAkademik::query()
            ->orderByDesc('tahun')
            ->orderByDesc('semester')
            ->get(['id', 'tahun', 'semester', 'status']);
        $tahunAkademikAktif = $tahunAkademik->firstWhere('status', true) ?? $tahunAkademik->first();
        $tahunAkademikId = $request->integer('tahun_akademik_id') ?: $tahunAkademikAktif?->id;

        $krs = Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->when($tahunAkademikId, fn ($query) => $query->whereHas('kelasKuliah', fn ($kelas) => $kelas->where('tahun_akademik_id', $tahunAkademikId)))
            ->with(['kelasKuliah.mataKuliah', 'kelasKuliah.tahunAkademik'])
            ->get();
        $bobotNilai = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'E' => 0];
        $krsDinilai = $krs->filter(fn (Krs $item): bool => isset($bobotNilai[strtoupper((string) $item->nilai)]) && ($item->kelasKuliah?->mataKuliah?->sks ?? 0) > 0);
        $totalSksDinilai = $krsDinilai->sum(fn (Krs $item): int => $item->kelasKuliah->mataKuliah->sks);
        $totalMutu = $krsDinilai->sum(fn (Krs $item): int => $item->kelasKuliah->mataKuliah->sks * $bobotNilai[strtoupper($item->nilai)]);

        return Inertia::render('Mahasiswa/HasilStudi', [
            'krs' => $krs,
            'tahunAkademiks' => $tahunAkademik,
            'tahunAkademikTerpilih' => $tahunAkademikId,
            'ringkasan' => [
                'totalSks' => $krs->sum(fn (Krs $item): int => $item->kelasKuliah?->mataKuliah?->sks ?? 0),
                'totalSksDinilai' => $totalSksDinilai,
                'totalMutu' => $totalMutu,
                'ip' => $totalSksDinilai > 0 ? round($totalMutu / $totalSksDinilai, 2) : null,
            ],
        ]);
    }
}
