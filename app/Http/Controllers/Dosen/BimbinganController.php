<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\NilaiPendadaran;
use App\Models\Pendadaran;
use App\Models\PengajuanAkademik;
use App\Models\SkalaNilai;
use App\Models\TugasAkhir;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mahasiswa bimbingan tugas akhir, persetujuan pendaftaran pendadaran, dan jadwal pendadaran dosen yang login.
 */
class BimbinganController extends Controller
{
    public function index(Request $request): Response
    {
        $dosen = $this->dosen($request);

        $bimbingan = TugasAkhir::query()
            ->dibimbing($dosen->id)
            ->with(['mahasiswa:id,user_id,nim,prodi_id', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang', 'pembimbing1.user:id,name', 'pembimbing2.user:id,name', 'pengajuan:id,lampiran'])
            // Yang masih berjalan di atas.
            ->orderByRaw('status = ? desc', [TugasAkhir::BERJALAN])
            ->latest('id')
            ->get();

        $menunggu = PengajuanAkademik::query()
            ->where('jenis', PengajuanAkademik::PENDADARAN)
            ->where('status', PengajuanAkademik::MENUNGGU_PEMBIMBING)
            ->whereIn('tugas_akhir_id', $bimbingan->pluck('id'))
            ->with(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name'])
            ->oldest('diajukan_at')
            ->get();

        $jadwal = Pendadaran::query()
            ->where(fn (Builder $q) => $q->diuji($dosen->id)->orWhereIn('tugas_akhir_id', $bimbingan->pluck('id')))
            ->with(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name', 'tugasAkhir:id,judul', 'ruang', 'penguji1.user:id,name', 'penguji2.user:id,name', 'penguji3.user:id,name', 'pengajuan:id,lampiran', 'nilai'])
            // Yang masih perlu ditindaklanjuti (dinilai/direvisi) di atas.
            ->orderByRaw('status in (?, ?) desc', Pendadaran::AKTIF)
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->get();

        return Inertia::render('Dosen/Bimbingan', [
            'bimbingan' => $bimbingan->map(fn (TugasAkhir $ta): array => [
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
            ]),
            'menungguPersetujuan' => $menunggu->map(fn (PengajuanAkademik $p): array => [
                'id' => $p->id,
                'nama' => $p->mahasiswa?->user?->name,
                'nim' => $p->mahasiswa?->nim,
                'judul' => $p->isian['judul'] ?? null,
                'lampiran' => array_keys($p->lampiran ?? []),
                'diajukan_at' => $p->diajukan_at?->toIso8601String(),
            ]),
            'jadwalPendadaran' => $jadwal->map(fn (Pendadaran $p): array => [
                ...$p->jadwal(),
                'nama' => $p->mahasiswa?->user?->name,
                'nim' => $p->mahasiswa?->nim,
                'judul' => $p->tugasAkhir?->judul,
                ...$p->ringkasanHasil(),
                'peran' => $p->peranPenguji($dosen->id) ?? 'Pembimbing',
                'pengajuan_id' => $p->pengajuan_id,
                'lampiran' => array_keys($p->pengajuan?->lampiran ?? []),
                'boleh_dinilai' => $p->peranPenguji($dosen->id) !== null && $p->bolehDinilai(),
                'nilai_saya' => $p->nilai->firstWhere('dosen_id', $dosen->id)?->only(['nilai', 'catatan']),
                'jumlah_nilai' => $p->nilai->count(),
                // Rincian nilai dan usulan hasil hanya untuk ketua penguji, yang menetapkan hasil.
                'ketua' => $ketua = $p->penguji_1_id === $dosen->id,
                'nilai_penguji' => $ketua ? $p->nilai->map(fn (NilaiPendadaran $n): array => [
                    'peran' => Pendadaran::PERAN_PENGUJI[$n->penguji_ke], 'nilai' => $n->nilai, 'catatan' => $n->catatan,
                ])->values() : [],
                'usulan' => $ketua && ($rata = $p->rataRata()) !== null ? [
                    'rata_rata' => $rata,
                    'huruf' => $huruf = SkalaNilai::dariAngka($rata),
                    'lulus' => $huruf !== null && SkalaNilai::lulus($huruf),
                ] : null,
            ]),
        ]);
    }

    /**
     * Pembimbing menyetujui pendaftaran pendadaran; pengajuan naik ke admin untuk dijadwalkan.
     */
    public function setujui(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        $dosen = $this->dosen($request);

        return $this->proses($pengajuanAkademik, $dosen, function (PengajuanAkademik $p) use ($request, $dosen): string {
            $p->update(['disetujui_pembimbing_oleh' => $dosen->id, 'disetujui_pembimbing_at' => now()]);
            $p->catat(PengajuanAkademik::MENUNGGU, null, $request->user()->id, PengajuanAkademik::DISETUJUI_PEMBIMBING);

            return 'Pendaftaran pendadaran disetujui dan diteruskan ke admin untuk dijadwalkan.';
        });
    }

    public function perbaikan(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        return $this->kembalikan($request, $pengajuanAkademik, PengajuanAkademik::PERLU_PERBAIKAN, 'Pendaftaran dikembalikan ke mahasiswa untuk diperbaiki.');
    }

    public function tolak(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        return $this->kembalikan($request, $pengajuanAkademik, PengajuanAkademik::DITOLAK, 'Pendaftaran pendadaran ditolak.');
    }

    private function kembalikan(Request $request, PengajuanAkademik $pengajuan, string $status, string $pesan): RedirectResponse
    {
        $dosen = $this->dosen($request);
        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']], attributes: ['catatan' => 'Catatan']);

        return $this->proses($pengajuan, $dosen, function (PengajuanAkademik $p) use ($request, $status, $pesan, $data): string {
            $p->catat($status, $data['catatan'], $request->user()->id);

            return $pesan;
        });
    }

    /**
     * @param  callable(PengajuanAkademik): string  $aksi
     */
    private function proses(PengajuanAkademik $pengajuan, DosenProfile $dosen, callable $aksi): RedirectResponse
    {
        abort_unless($pengajuan->jenis === PengajuanAkademik::PENDADARAN && $pengajuan->tugasAkhir?->dibimbingOleh($dosen->id), 404);

        return DB::transaction(function () use ($pengajuan, $aksi): RedirectResponse {
            $pengajuan = PengajuanAkademik::query()->lockForUpdate()->findOrFail($pengajuan->id);

            if ($pengajuan->status !== PengajuanAkademik::MENUNGGU_PEMBIMBING) {
                return back()->with('error', 'Pendaftaran ini sudah diproses.');
            }

            return back()->with('success', $aksi($pengajuan));
        });
    }

    private function dosen(Request $request): DosenProfile
    {
        $dosen = $request->user()->dosenProfile;
        abort_if($dosen === null, 403);

        return $dosen;
    }
}
