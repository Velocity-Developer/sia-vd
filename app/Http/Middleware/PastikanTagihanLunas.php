<?php

namespace App\Http\Middleware;

use App\Models\PengaturanAkademik;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengunci pengisian KRS selama tagihan semester berjalan belum lunas.
 *
 * Semester yang tagihannya belum diterbitkan tidak mengunci apa pun, supaya menyalakan fitur ini
 * tidak mematikan akses mahasiswa yang memang belum pernah ditagih.
 */
class PastikanTagihanLunas
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PengaturanAkademik::current()->kunci_krs_aktif) {
            return $next($request);
        }

        $mahasiswa = $request->user()?->mahasiswaProfile;

        if ($mahasiswa === null) {
            return $next($request);
        }

        $tahunAkademik = TahunAkademik::query()->where('status', true)->first();

        $tagihan = $tahunAkademik === null ? null : TagihanSemester::query()
            ->with('items')
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $tahunAkademik->id)
            ->first();

        if ($tagihan === null || $tagihan->lunas()) {
            return $next($request);
        }

        if (! $request->isMethod('GET')) {
            return back()->with('krs_error', 'Tagihan semester ini belum lunas, pengisian KRS masih terkunci.');
        }

        return Inertia::render('Mahasiswa/KrsTerkunci', [
            'tahunAkademik' => $tahunAkademik->tahun.' '.$tahunAkademik->semester,
            'total' => $tagihan->total,
            'items' => $tagihan->items->map(fn ($item): array => [
                'nama' => $item->nama,
                'subtotal' => $item->subtotal,
            ])->all(),
            'batasKrs' => $tahunAkademik->tanggal_krs_akhir,
        ])->toResponse($request);
    }
}
