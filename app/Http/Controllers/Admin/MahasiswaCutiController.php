<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanAkademik;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mahasiswa Cuti: daftar mahasiswa yang berstatus Cuti, dengan cuti terakhir yang disetujui (semester, alasan, tanggal
 * disetujui), jumlah cuti selama studi, dan pengajuan aktif kembali yang sedang menunggu. Baca saja; status berubah
 * lewat Pengajuan Cuti.
 */
class MahasiswaCutiController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = [
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $mahasiswa = MahasiswaProfile::query()
            ->where('status', 'Cuti')
            ->when($filter['prodi_id'], fn (Builder $q, int $prodi) => $q->where('prodi_id', $prodi))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $m) => $m->where('nim', 'like', "%{$filter['search']}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$filter['search']}%"))))
            ->with(['user:id,name', 'prodi:id,nama_prodi,jenjang'])
            ->orderBy('nim')
            ->paginate(25, ['id', 'user_id', 'nim', 'prodi_id', 'angkatan'])
            ->withQueryString();

        $pengajuan = PengajuanAkademik::query()->whereIn('mahasiswa_id', $mahasiswa->getCollection()->pluck('id'))
            ->whereIn('jenis', PengajuanAkademik::JENIS_CUTI)->latest('id')->get()->groupBy('mahasiswa_id');
        $tahun = TahunAkademik::query()->get()->keyBy('id');

        $mahasiswa->through(function (MahasiswaProfile $m) use ($pengajuan, $tahun): array {
            $milik = $pengajuan->get($m->id, collect());
            $cuti = $milik->where('jenis', PengajuanAkademik::CUTI)->where('status', PengajuanAkademik::DISETUJUI);
            $terakhir = $cuti->first();

            return [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'nim' => $m->nim,
                'nama' => $m->user?->name,
                'prodi' => $m->prodi ? $m->prodi->jenjang.' '.$m->prodi->nama_prodi : null,
                'angkatan' => $m->angkatan,
                'tahun_akademik' => $tahun->get($terakhir?->isian['tahun_akademik_id'] ?? 0)?->label(),
                'alasan' => $terakhir?->isian['alasan'] ?? null,
                'disetujui_at' => $terakhir?->diproses_at?->toIso8601String(),
                'jumlah_cuti' => $cuti->count(),
                'aktif_kembali_menunggu' => $milik->contains(fn (PengajuanAkademik $p): bool => $p->jenis === PengajuanAkademik::AKTIF_KEMBALI && $p->menunggu()),
            ];
        });

        return Inertia::render('Admin/MahasiswaCuti', [
            'mahasiswa' => $mahasiswa,
            'filter' => $filter,
            'prodiOptions' => ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang'])
                ->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => $p->jenjang.' '.$p->nama_prodi]),
        ]);
    }
}
