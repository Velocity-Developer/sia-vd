<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jadwal ujian kelas yang diampu dosen (termasuk yang masih draf, agar soal bisa disiapkan lebih awal).
 */
class UjianController extends Controller
{
    public function index(Request $request): Response
    {
        $dosenId = $request->user()->dosenProfile?->id;
        abort_if($dosenId === null, 403);
        $tahunAkademiks = TahunAkademik::orderByDesc('tanggal_mulai')->get(['id', 'tahun', 'semester', 'status']);
        $tahunId = $request->integer('tahun_akademik_id') ?: $tahunAkademiks->firstWhere('status', true)?->id;

        $ujians = Ujian::query()
            ->whereHas('kelasKuliah', fn (Builder $k) => $k->where('dosen_id', $dosenId)->where('tahun_akademik_id', $tahunId))
            ->with([
                'kelasKuliah' => fn ($q) => $q->select(['id', 'kode_kelas', 'matkul_id'])->withCount('krs'),
                'kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul',
                'ruang:id,kode_ruang,nama_ruang',
            ])
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->get();

        return Inertia::render('Dosen/Ujian', [
            'ujians' => $ujians,
            'tahunAkademikId' => $tahunId,
            'tahunAkademikOptions' => $tahunAkademiks->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->tahun.' '.$t->semester]),
        ]);
    }
}
