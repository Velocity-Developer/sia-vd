<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\GelombangKompre;
use App\Models\PengajuanAkademik;
use App\Models\PeriodeWisuda;
use App\Models\TahunAkademik;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengajuan & Pendaftaran untuk dosen: daftar pengajuan semua jenis milik mahasiswa bimbingan akademik (PA) dosen yang
 * login. Hanya dilihat; pengajuan diproses admin.
 */
class PengajuanMahasiswaPaController extends Controller
{
    public function index(Request $request): Response
    {
        $dosen = $request->user()->dosenProfile;
        abort_if($dosen === null, 403);

        $jenisTersedia = array_keys(PengajuanAkademik::LABEL_JENIS);
        $filter = [
            'jenis' => in_array($request->query('jenis'), $jenisTersedia, true) ? $request->query('jenis') : null,
            'status' => in_array($request->query('status'), PengajuanAkademik::STATUS, true) ? $request->query('status') : null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $pengajuan = PengajuanAkademik::query()
            ->whereHas('mahasiswa', fn (Builder $m) => $m->where('dosen_wali_id', $dosen->id))
            ->when($filter['jenis'], fn (Builder $q, string $jenis) => $q->where('jenis', $jenis))
            ->when($filter['status'], fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->whereHas('mahasiswa', fn (Builder $m) => $m->where('nim', 'like', "%{$filter['search']}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$filter['search']}%"))))
            ->with(['mahasiswa:id,user_id,nim,prodi_id', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang', 'pemroses:id,name'])
            ->latest('diajukan_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $isian = $pengajuan->getCollection()->pluck('isian');
        $tahun = TahunAkademik::query()->whereIn('id', $isian->pluck('tahun_akademik_id')->filter())->get()->keyBy('id');
        $periode = PeriodeWisuda::query()->whereIn('id', $isian->pluck('periode_wisuda_id')->filter())->pluck('nama', 'id');
        $gelombang = GelombangKompre::query()->whereIn('id', $isian->pluck('gelombang_kompre_id')->filter())->pluck('nama', 'id');

        $pengajuan->through(fn (PengajuanAkademik $p): array => [
            'id' => $p->id,
            'nama' => $p->mahasiswa?->user?->name,
            'nim' => $p->mahasiswa?->nim,
            'prodi' => $p->mahasiswa?->prodi ? $p->mahasiswa->prodi->jenjang.' '.$p->mahasiswa->prodi->nama_prodi : null,
            'jenis' => PengajuanAkademik::JENIS_KKM[$p->isian['jenis_kkm'] ?? ''] ?? PengajuanAkademik::LABEL_JENIS[$p->jenis] ?? $p->jenis,
            'ringkasan' => $this->ringkasan($p, $tahun, $periode, $gelombang),
            'keterangan' => $p->isian['alasan'] ?? $p->isian['keterangan'] ?? $p->isian['ringkasan'] ?? null,
            'lampiran' => array_keys($p->lampiran ?? []),
            'status' => $p->status,
            'catatan' => $p->catatan,
            'diproses_oleh' => $p->pemroses?->name,
            'diproses_at' => $p->diproses_at?->toIso8601String(),
            'diajukan_at' => $p->diajukan_at?->toIso8601String(),
        ]);

        return Inertia::render('Dosen/PengajuanMahasiswaPa', [
            'pengajuan' => $pengajuan,
            'filter' => $filter,
            'jenisOptions' => collect(PengajuanAkademik::LABEL_JENIS)->map(fn (string $label, string $id): array => ['id' => $id, 'name' => $label])->values(),
        ]);
    }

    /**
     * Isi pokok pengajuan dalam satu baris: judul, semester cuti, periode wisuda, atau gelombang kompre.
     *
     * @param  Collection<int, TahunAkademik>  $tahun
     * @param  Collection<int, string>  $periode
     * @param  Collection<int, string>  $gelombang
     */
    private function ringkasan(PengajuanAkademik $p, $tahun, $periode, $gelombang): ?string
    {
        return match ($p->jenis) {
            PengajuanAkademik::CUTI => ($t = $tahun->get($p->isian['tahun_akademik_id'] ?? 0)) ? 'Semester '.$t->label() : null,
            PengajuanAkademik::WISUDA => $periode[$p->isian['periode_wisuda_id'] ?? 0] ?? null,
            PengajuanAkademik::KOMPRE => trim(($gelombang[$p->isian['gelombang_kompre_id'] ?? 0] ?? '').' · '.($p->isian['judul'] ?? ''), ' ·'),
            default => $p->isian['judul'] ?? null,
        };
    }
}
