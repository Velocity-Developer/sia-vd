<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\TugasAkhir;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mahasiswa bimbingan tugas akhir milik dosen yang login.
 */
class BimbinganController extends Controller
{
    public function index(Request $request): Response
    {
        $dosen = $request->user()->dosenProfile;
        abort_if($dosen === null, 403);

        $bimbingan = TugasAkhir::query()
            ->dibimbing($dosen->id)
            ->with(['mahasiswa:id,user_id,nim,prodi_id', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang', 'pembimbing1.user:id,name', 'pembimbing2.user:id,name', 'pengajuan:id,lampiran'])
            // Yang masih berjalan di atas.
            ->orderByRaw('status = ? desc', [TugasAkhir::BERJALAN])
            ->latest('id')
            ->get()
            ->map(fn (TugasAkhir $ta): array => [
                'id' => $ta->id,
                'nama' => $ta->mahasiswa?->user?->name,
                'nim' => $ta->mahasiswa?->nim,
                'prodi' => $ta->mahasiswa?->prodi ? $ta->mahasiswa->prodi->jenjang.' '.$ta->mahasiswa->prodi->nama_prodi : null,
                'judul' => $ta->judul,
                'bidang' => $ta->bidang,
                'peran' => $ta->pembimbing_1_id === $dosen->id ? 'Pembimbing 1' : 'Pembimbing 2',
                'pembimbing_lain' => $ta->pembimbing_1_id === $dosen->id ? $ta->pembimbing2?->user?->name : $ta->pembimbing1?->user?->name,
                'status' => $ta->status,
                'proposal_pengajuan_id' => isset($ta->pengajuan?->lampiran['proposal']) ? $ta->pengajuan_id : null,
                'disahkan_at' => $ta->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Dosen/Bimbingan', ['bimbingan' => $bimbingan]);
    }
}
