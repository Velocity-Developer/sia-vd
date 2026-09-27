<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\PengajuanAkademik;
use App\Models\TugasAkhir;
use App\SyaratTugasAkhir;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengajuan TA, pendadaran, dan wisuda: admin menyetujui, meminta perbaikan, atau menolak.
 */
class PengajuanAkademikController extends Controller
{
    /** Jenis yang sudah bisa diproses; tab lain menyusul. */
    public const JENIS_TERSEDIA = [PengajuanAkademik::TUGAS_AKHIR];

    public function index(Request $request): Response
    {
        $jenis = in_array($request->query('jenis'), self::JENIS_TERSEDIA, true) ? $request->query('jenis') : PengajuanAkademik::TUGAS_AKHIR;
        $filter = [
            'jenis' => $jenis,
            'status' => in_array($request->query('status'), PengajuanAkademik::STATUS, true) ? $request->query('status') : null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $dosen = collect(DosenProfile::opsi())->pluck('name', 'id');
        $pengajuan = PengajuanAkademik::query()
            ->where('jenis', $jenis)
            ->when($filter['status'], fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->whereHas('mahasiswa', fn (Builder $m) => $m->where('nim', 'like', "%{$filter['search']}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$filter['search']}%"))))
            ->with(['mahasiswa:id,user_id,nim,prodi_id', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang', 'pemroses:id,name'])
            // Yang menunggu keputusan tampil paling atas, yang paling lama menunggu lebih dulu.
            ->orderByRaw('status = ? desc', [PengajuanAkademik::MENUNGGU])
            ->orderByRaw('case when status = ? then diajukan_at end asc', [PengajuanAkademik::MENUNGGU])
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PengajuanAkademik $p): array => [
                'id' => $p->id,
                'nama' => $p->mahasiswa?->user?->name,
                'nim' => $p->mahasiswa?->nim,
                'prodi' => $p->mahasiswa?->prodi ? $p->mahasiswa->prodi->jenjang.' '.$p->mahasiswa->prodi->nama_prodi : null,
                'isian' => $p->isian,
                'usulan_pembimbing' => array_values(array_filter([
                    $dosen[$p->isian['usulan_pembimbing_1_id'] ?? 0] ?? null,
                    $dosen[$p->isian['usulan_pembimbing_2_id'] ?? 0] ?? null,
                ])),
                'lampiran' => array_keys($p->lampiran ?? []),
                'status' => $p->status,
                'catatan' => $p->catatan,
                'diproses_oleh' => $p->pemroses?->name,
                'diproses_at' => $p->diproses_at?->toIso8601String(),
                'diajukan_at' => $p->diajukan_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/PengajuanAkademik', [
            'pengajuan' => $pengajuan,
            'filter' => $filter,
            'jenisTersedia' => self::JENIS_TERSEDIA,
            'jumlahMenunggu' => PengajuanAkademik::query()->where('status', PengajuanAkademik::MENUNGGU)
                ->whereIn('jenis', self::JENIS_TERSEDIA)->selectRaw('jenis, count(*) as jumlah')->groupBy('jenis')->pluck('jumlah', 'jenis'),
            'dosenOptions' => DosenProfile::opsi(),
        ]);
    }

    public function setujui(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        abort_unless(in_array($pengajuanAkademik->jenis, self::JENIS_TERSEDIA, true), 404);

        return $this->setujuiTa($request, $pengajuanAkademik);
    }

    public function perbaikan(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        return $this->kembalikan($request, $pengajuanAkademik, PengajuanAkademik::PERLU_PERBAIKAN, 'Pengajuan dikembalikan ke mahasiswa untuk diperbaiki.');
    }

    public function tolak(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        return $this->kembalikan($request, $pengajuanAkademik, PengajuanAkademik::DITOLAK, 'Pengajuan ditolak.');
    }

    /**
     * Setujui pengajuan TA sekaligus sahkan judul dan tetapkan pembimbing (boleh berbeda dari usulan).
     */
    private function setujuiTa(Request $request, PengajuanAkademik $pengajuan): RedirectResponse
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:300'],
            'pembimbing_1_id' => ['required', 'integer', Rule::exists('dosen_profiles', 'id')],
            'pembimbing_2_id' => ['nullable', 'integer', Rule::exists('dosen_profiles', 'id'), 'different:pembimbing_1_id'],
        ], ['pembimbing_2_id.different' => 'Pembimbing 2 harus berbeda dari pembimbing 1.'], [
            'judul' => 'Judul',
            'pembimbing_1_id' => 'Pembimbing 1',
            'pembimbing_2_id' => 'Pembimbing 2',
        ]);

        return DB::transaction(function () use ($request, $pengajuan, $data): RedirectResponse {
            $pengajuan = PengajuanAkademik::query()->lockForUpdate()->findOrFail($pengajuan->id);

            if (! $pengajuan->menunggu()) {
                return back()->with('error', 'Pengajuan ini sudah diproses.');
            }
            if (TugasAkhir::milik($pengajuan->mahasiswa_id) !== null) {
                return back()->with('error', 'Mahasiswa ini sudah punya tugas akhir yang disahkan.');
            }
            // Diperiksa ulang: mata kuliah TA bisa saja dibatalkan dari KRS sesudah pengajuan dikirim.
            if (! SyaratTugasAkhir::terpenuhi(SyaratTugasAkhir::pengajuanTa($pengajuan->mahasiswa))) {
                return back()->with('error', 'Mahasiswa ini tidak lagi mengambil mata kuliah TA/Skripsi di semester aktif. Minta perbaikan atau tolak pengajuannya.');
            }

            TugasAkhir::query()->create([
                'mahasiswa_id' => $pengajuan->mahasiswa_id,
                'pengajuan_id' => $pengajuan->id,
                'judul' => $data['judul'],
                'bidang' => $pengajuan->isian['bidang'] ?? '-',
                'pembimbing_1_id' => $data['pembimbing_1_id'],
                'pembimbing_2_id' => $data['pembimbing_2_id'] ?? null,
                'status' => TugasAkhir::BERJALAN,
                'disahkan_oleh' => $request->user()->id,
            ]);
            $pengajuan->catat(PengajuanAkademik::DISETUJUI, null, $request->user()->id);

            return back()->with('success', 'Pengajuan tugas akhir disetujui; judul dan pembimbing sudah disahkan.');
        });
    }

    private function kembalikan(Request $request, PengajuanAkademik $pengajuan, string $status, string $pesan): RedirectResponse
    {
        abort_unless(in_array($pengajuan->jenis, self::JENIS_TERSEDIA, true), 404);
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
