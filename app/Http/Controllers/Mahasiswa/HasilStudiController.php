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
    public function transkrip(Request $request): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        $krs = Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereNotNull('nilai')
            ->with('kelasKuliah.mataKuliah', 'kelasKuliah.tahunAkademik')
            ->get();
        $bobotNilai = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'E' => 0];
        $transkrip = $krs->filter(fn (Krs $item): bool => isset($bobotNilai[strtoupper((string) $item->nilai)]) && ($item->kelasKuliah?->mataKuliah?->sks ?? 0) > 0)
            ->map(fn (Krs $item): array => [
                'id' => $item->id,
                'kode' => $item->kelasKuliah->mataKuliah->kode_matkul,
                'nama' => $item->kelasKuliah->mataKuliah->nama_matkul,
                'jenis' => $item->kelasKuliah->mataKuliah->jenis,
                'sks' => $item->kelasKuliah->mataKuliah->sks,
                'nilai' => strtoupper($item->nilai),
                'bobot' => $bobotNilai[strtoupper($item->nilai)],
                'mutu' => $item->kelasKuliah->mataKuliah->sks * $bobotNilai[strtoupper($item->nilai)],
            ])->values();
        $totalSks = $transkrip->sum('sks');
        $totalMutu = $transkrip->sum('mutu');

        return Inertia::render('Mahasiswa/TranskripNilai', [
            'transkrip' => $transkrip,
            'ringkasan' => ['totalMatkul' => $transkrip->count(), 'totalSks' => $totalSks, 'totalMutu' => $totalMutu, 'ipk' => $totalSks > 0 ? round($totalMutu / $totalSks, 2) : null],
        ]);
    }

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
