<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanAkademik;
use App\Models\PengaturanAkademik;
use App\Models\TahunAkademik;
use App\PengajuanCuti;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengajuan cuti dan aktif kembali: admin menyetujui (status mahasiswa berubah), meminta perbaikan, atau menolak.
 */
class PengajuanCutiController extends Controller
{
    public function index(Request $request): Response
    {
        $jenis = in_array($request->query('jenis'), PengajuanAkademik::JENIS_CUTI, true) ? $request->query('jenis') : PengajuanAkademik::CUTI;
        $filter = [
            'jenis' => $jenis,
            'status' => in_array($request->query('status'), PengajuanAkademik::STATUS, true) ? $request->query('status') : null,
            'search' => $request->string('search')->trim()->toString(),
        ];
        $tahun = TahunAkademik::query()->get()->keyBy('id');

        $pengajuan = PengajuanAkademik::query()
            ->where('jenis', $jenis)
            ->when($filter['status'], fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->whereHas('mahasiswa', fn (Builder $m) => $m->where('nim', 'like', "%{$filter['search']}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$filter['search']}%"))))
            ->with(['mahasiswa:id,user_id,nim,prodi_id,status', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang', 'pemroses:id,name'])
            // Yang menunggu keputusan tampil paling atas, yang paling lama menunggu lebih dulu.
            ->orderByRaw('status = ? desc', [PengajuanAkademik::MENUNGGU])
            ->orderByRaw('case when status = ? then diajukan_at end asc', [PengajuanAkademik::MENUNGGU])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
        $jumlahCuti = PengajuanAkademik::query()->where('jenis', PengajuanAkademik::CUTI)->where('status', PengajuanAkademik::DISETUJUI)
            ->whereIn('mahasiswa_id', $pengajuan->getCollection()->pluck('mahasiswa_id'))
            ->selectRaw('mahasiswa_id, count(*) as jumlah')->groupBy('mahasiswa_id')->pluck('jumlah', 'mahasiswa_id');

        $pengajuan->through(fn (PengajuanAkademik $p): array => [
            'id' => $p->id,
            'nama' => $p->mahasiswa?->user?->name,
            'nim' => $p->mahasiswa?->nim,
            'prodi' => $p->mahasiswa?->prodi ? $p->mahasiswa->prodi->jenjang.' '.$p->mahasiswa->prodi->nama_prodi : null,
            'status_mahasiswa' => $p->mahasiswa?->status,
            'jumlah_cuti' => (int) ($jumlahCuti[$p->mahasiswa_id] ?? 0),
            'isian' => $p->isian,
            'tahun_akademik' => $tahun->get($p->isian['tahun_akademik_id'] ?? 0)?->label(),
            'tahun_aktif' => (bool) $tahun->get($p->isian['tahun_akademik_id'] ?? 0)?->status,
            'lampiran' => array_keys($p->lampiran ?? []),
            'status' => $p->status,
            'catatan' => $p->catatan,
            'diproses_oleh' => $p->pemroses?->name,
            'diproses_at' => $p->diproses_at?->toIso8601String(),
            'diajukan_at' => $p->diajukan_at?->toIso8601String(),
        ]);

        return Inertia::render('Admin/PengajuanCuti', [
            'pengajuan' => $pengajuan,
            'filter' => $filter,
            'maksCuti' => PengaturanAkademik::current()->maks_cuti,
            'jumlahMenunggu' => PengajuanAkademik::query()->where('status', PengajuanAkademik::MENUNGGU)
                ->whereIn('jenis', PengajuanAkademik::JENIS_CUTI)->selectRaw('jenis, count(*) as jumlah')->groupBy('jenis')->pluck('jumlah', 'jenis'),
        ]);
    }

    public function setujui(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        abort_unless(in_array($pengajuanAkademik->jenis, PengajuanAkademik::JENIS_CUTI, true), 404);

        return DB::transaction(function () use ($request, $pengajuanAkademik): RedirectResponse {
            $pengajuan = PengajuanAkademik::query()->lockForUpdate()->findOrFail($pengajuanAkademik->id);
            $mahasiswa = MahasiswaProfile::query()->with('user:id,name')->lockForUpdate()->findOrFail($pengajuan->mahasiswa_id);
            $nama = $mahasiswa->user?->name;

            if (! $pengajuan->menunggu()) {
                return back()->with('error', 'Pengajuan ini sudah diproses.');
            }

            if ($pengajuan->jenis === PengajuanAkademik::AKTIF_KEMBALI) {
                if (($alasan = PengajuanCuti::alasanTidakBolehAktifKembali($mahasiswa)) !== null) {
                    return back()->with('error', "Status {$nama} kini {$mahasiswa->status}. {$alasan} Tolak pengajuannya.");
                }
                $mahasiswa->update(['status' => 'Aktif']);
                $pengajuan->catat(PengajuanAkademik::DISETUJUI, null, $request->user()->id);

                return back()->with('success', "Pengajuan disetujui; status {$nama} kembali Aktif.");
            }

            // Diperiksa ulang: status, batas cuti, dan semester tujuan bisa berubah sejak pengajuan dikirim.
            $tahun = TahunAkademik::query()->find($pengajuan->isian['tahun_akademik_id'] ?? 0);
            $maks = PengaturanAkademik::current()->maks_cuti;
            $galat = match (true) {
                ! $mahasiswa->isAktif() => "Status {$nama} kini {$mahasiswa->status}, bukan Aktif.",
                PengajuanCuti::disetujui($mahasiswa->id)->count() >= $maks => "{$nama} sudah mencapai batas cuti {$maks} semester.",
                $tahun === null || $tahun->tanggal_akhir->lt(today()) => 'Semester yang dipilih sudah berakhir.',
                default => null,
            };
            if ($galat !== null) {
                return back()->with('error', $galat.' Minta perbaikan atau tolak pengajuannya.');
            }

            $pengajuan->catat(PengajuanAkademik::DISETUJUI, null, $request->user()->id);

            return back()->with('success', PengajuanCuti::terapkanPengajuan($pengajuan)
                ? "Cuti {$tahun->label()} disetujui; status {$nama} kini Cuti."
                : "Cuti {$tahun->label()} disetujui; status {$nama} berubah menjadi Cuti saat semester itu diaktifkan.");
        });
    }

    public function perbaikan(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        return $this->kembalikan($request, $pengajuanAkademik, PengajuanAkademik::PERLU_PERBAIKAN, 'Pengajuan dikembalikan ke mahasiswa untuk diperbaiki.');
    }

    public function tolak(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        return $this->kembalikan($request, $pengajuanAkademik, PengajuanAkademik::DITOLAK, 'Pengajuan ditolak.');
    }

    private function kembalikan(Request $request, PengajuanAkademik $pengajuan, string $status, string $pesan): RedirectResponse
    {
        abort_unless(in_array($pengajuan->jenis, PengajuanAkademik::JENIS_CUTI, true), 404);
        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']], attributes: ['catatan' => 'Catatan']);

        return DB::transaction(function () use ($request, $pengajuan, $status, $pesan, $data): RedirectResponse {
            $pengajuan = PengajuanAkademik::query()->lockForUpdate()->findOrFail($pengajuan->id);

            if (! $pengajuan->menunggu()) {
                return back()->with('error', 'Pengajuan ini sudah diproses.');
            }

            $pengajuan->catat($status, $data['catatan'], $request->user()->id);

            return back()->with('success', $pesan);
        });
    }
}
