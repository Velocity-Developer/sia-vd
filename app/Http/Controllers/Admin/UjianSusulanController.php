<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengajuanSusulan;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use App\UjianSusulan;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengajuan ujian susulan: admin menyetujui atau menolak. Tagihan dan jadwal susulan menyusul setelah disetujui.
 */
class UjianSusulanController extends Controller
{
    public function index(Request $request): Response
    {
        $tahunAkademiks = TahunAkademik::orderByDesc('tanggal_mulai')->get(['id', 'tahun', 'semester', 'status']);
        $filter = [
            'tahun_akademik_id' => $request->integer('tahun_akademik_id') ?: $tahunAkademiks->firstWhere('status', true)?->id,
            'status' => in_array($request->query('status'), [PengajuanSusulan::MENUNGGU, PengajuanSusulan::DISETUJUI, PengajuanSusulan::DITOLAK, PengajuanSusulan::DIBATALKAN], true) ? $request->query('status') : null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $pengajuan = PengajuanSusulan::query()
            ->whereHas('ujian.kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $filter['tahun_akademik_id']))
            ->when($filter['status'], fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->whereHas('mahasiswa', fn (Builder $m) => $m->where('nim', 'like', "%{$filter['search']}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$filter['search']}%"))))
            ->with(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name', 'ujian:id,kelas_id,jenis,mode,tanggal,jam_mulai,jam_akhir,status', 'ujian.kelasKuliah:id,kode_kelas,matkul_id', 'ujian.kelasKuliah.mataKuliah:id,nama_matkul', 'pemroses:id,name'])
            // Yang menunggu keputusan tampil paling atas.
            ->orderByRaw('status = ? desc', [PengajuanSusulan::MENUNGGU])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Keikutsertaan di ujian utama dihitung sekali per ujian, bukan per pengajuan.
        $peserta = $pengajuan->getCollection()->pluck('ujian')->unique('id')
            ->mapWithKeys(fn (Ujian $u): array => [$u->id => UjianSusulan::pesertaUjianUtama($u)->flip()]);

        $pengajuan->through(fn (PengajuanSusulan $p): array => [
            'id' => $p->id,
            'nama' => $p->mahasiswa?->user?->name,
            'nim' => $p->mahasiswa?->nim,
            'kelas' => $p->ujian?->kelasKuliah?->kode_kelas,
            'matkul' => $p->ujian?->kelasKuliah?->mataKuliah?->nama_matkul,
            'jenis' => $p->ujian?->jenis,
            'tanggal_ujian' => $p->ujian?->tanggal->toDateString(),
            'alasan' => $p->alasan,
            'jumlah_lampiran' => count($p->lampiran ?? []),
            'status' => $p->statusTampil($peserta[$p->ujian_id]->has($p->mahasiswa_id)),
            'ikut_ujian_utama' => $peserta[$p->ujian_id]->has($p->mahasiswa_id),
            'catatan_admin' => $p->catatan_admin,
            'diproses_oleh' => $p->pemroses?->name,
            'diproses_at' => $p->diproses_at?->toIso8601String(),
            'diajukan_at' => $p->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Admin/UjianSusulan', [
            'pengajuan' => $pengajuan,
            'filter' => $filter,
            'jumlahMenunggu' => PengajuanSusulan::query()->where('status', PengajuanSusulan::MENUNGGU)
                ->whereHas('ujian.kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $filter['tahun_akademik_id']))->count(),
            'tahunAkademikOptions' => $tahunAkademiks->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->tahun.' '.$t->semester]),
        ]);
    }

    public function setujui(Request $request, PengajuanSusulan $pengajuanSusulan): RedirectResponse
    {
        if ($pengajuanSusulan->status !== PengajuanSusulan::MENUNGGU) {
            return back()->with('error', 'Pengajuan ini sudah diproses.');
        }

        // Diperiksa ulang: pengajuan bisa dikirim sebelum ujian, lalu mahasiswanya tetap ikut.
        if (UjianSusulan::ikutUjianUtama($pengajuanSusulan->ujian, $pengajuanSusulan->mahasiswa_id)) {
            return back()->with('error', 'Mahasiswa ini ternyata sudah mengikuti ujian utama, jadi tidak perlu ujian susulan.');
        }

        $pengajuanSusulan->update([
            'status' => PengajuanSusulan::DISETUJUI,
            'diproses_oleh' => $request->user()->id,
            'diproses_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan ujian susulan disetujui. Terbitkan tagihannya di menu Tagihan Susulan.');
    }

    public function tolak(Request $request, PengajuanSusulan $pengajuanSusulan): RedirectResponse
    {
        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']], attributes: ['catatan' => 'Alasan penolakan']);

        if ($pengajuanSusulan->status !== PengajuanSusulan::MENUNGGU) {
            return back()->with('error', 'Pengajuan ini sudah diproses.');
        }

        $pengajuanSusulan->update([
            'status' => PengajuanSusulan::DITOLAK,
            'catatan_admin' => $data['catatan'],
            'diproses_oleh' => $request->user()->id,
            'diproses_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan ujian susulan ditolak.');
    }
}
