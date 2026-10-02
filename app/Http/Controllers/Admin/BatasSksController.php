<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BatasSks;
use App\Models\PengaturanAkademik;
use App\Models\ProgramStudi;
use App\ValidasiBatasSks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Batas SKS per semester milik prodi. Aturannya sama dengan Batas SKS di Pengaturan Akademik; prodi yang belum
 * diatur memakai batas global itu (lihat PengaturanAkademik::maksSksUntuk()).
 */
class BatasSksController extends Controller
{
    public function index(Request $request): Response
    {
        $prodi = ProgramStudi::query()->withCount('batasSks')->orderBy('nama_prodi')->get(['id', 'kode_prodi', 'nama_prodi', 'jenjang', 'maks_sks_tanpa_ips']);
        $terpilih = $prodi->firstWhere('id', $request->integer('prodi')) ?? $prodi->first();
        $belumDiatur = $terpilih !== null && $terpilih->batas_sks_count === 0;

        return Inertia::render('Admin/BatasSks', [
            'prodi' => $prodi->map(fn (ProgramStudi $item): array => [
                'id' => $item->id,
                'nama' => trim(($item->jenjang ? $item->jenjang.' ' : '').$item->nama_prodi),
                'kode' => $item->kode_prodi,
                'jumlah' => $item->batas_sks_count,
            ]),
            'prodiId' => $terpilih?->id,
            'belumDiatur' => $belumDiatur,
            'maksSksTanpaIps' => $terpilih === null ? null
                : ($belumDiatur ? PengaturanAkademik::current()->maks_sks_tanpa_ips : $terpilih->maks_sks_tanpa_ips),
            'batasSks' => $terpilih === null ? []
                : ($belumDiatur ? BatasSks::query() : $terpilih->batasSks())->orderByDesc('ips_minimal')->get(['ips_minimal', 'maks_sks']),
        ]);
    }

    public function update(Request $request, ProgramStudi $programStudi): RedirectResponse
    {
        $data = ValidasiBatasSks::validasi($request);

        DB::transaction(function () use ($programStudi, $data): void {
            $programStudi->update(['maks_sks_tanpa_ips' => $data['maks_sks_tanpa_ips']]);
            $programStudi->batasSks()->delete();

            foreach ($data['batas_sks'] as $row) {
                $programStudi->batasSks()->create($row);
            }
        });

        return to_route('admin.batas-sks.index', ['prodi' => $programStudi->id])
            ->with('success', "Batas SKS {$programStudi->nama_prodi} berhasil disimpan.");
    }

    public function destroy(ProgramStudi $programStudi): RedirectResponse
    {
        DB::transaction(function () use ($programStudi): void {
            $programStudi->batasSks()->delete();
            $programStudi->update(['maks_sks_tanpa_ips' => null]);
        });

        return to_route('admin.batas-sks.index', ['prodi' => $programStudi->id])
            ->with('success', "Batas SKS {$programStudi->nama_prodi} dihapus; prodi ini kembali memakai Batas SKS global.");
    }
}
