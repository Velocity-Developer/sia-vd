<?php

namespace App\Http\Controllers\Kelas;

use App\Http\Controllers\Concerns\AksesPresensi;
use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\PengajuanIzin;
use App\Models\PresensiMahasiswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengajuan izin/sakit mahasiswa yang diperiksa dosen pengampu (atau admin).
 */
class PengajuanIzinController extends Controller
{
    use AksesPresensi, KontenKelas;

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), [PengajuanIzin::MENUNGGU, PengajuanIzin::DISETUJUI, PengajuanIzin::DITOLAK], true)
            ? $request->query('status')
            : PengajuanIzin::MENUNGGU;

        $pengajuan = PengajuanIzin::query()
            ->where('status', $status)
            ->when($this->peran() === 'dosen', fn ($q) => $q->whereHas('pertemuan.kelasKuliah', fn ($kelas) => $kelas->where('dosen_id', $this->dosenId())))
            ->with([
                'mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name', 'pemroses:id,name',
                'pertemuan:id,kelas_id,pertemuan_ke,tanggal,jam_mulai,jam_akhir,jenis,status',
                'pertemuan.kelasKuliah:id,kode_kelas,matkul_id', 'pertemuan.kelasKuliah.mataKuliah:id,nama_matkul',
            ])
            ->orderBy($status === PengajuanIzin::MENUNGGU ? 'created_at' : 'diproses_at', $status === PengajuanIzin::MENUNGGU ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        $statusPresensi = PresensiMahasiswa::query()
            ->whereIn('pertemuan_id', $pengajuan->getCollection()->pluck('pertemuan_id'))
            ->get(['pertemuan_id', 'mahasiswa_id', 'status'])
            ->mapWithKeys(fn (PresensiMahasiswa $p): array => [$p->pertemuan_id.'-'.$p->mahasiswa_id => $p->status]);

        $pengajuan->getCollection()->transform(fn (PengajuanIzin $item): array => [
            'id' => $item->id,
            'jenis' => $item->jenis,
            'alasan' => $item->alasan,
            'jumlah_lampiran' => count($item->lampiran ?? []),
            'status' => $item->status,
            'catatan_dosen' => $item->catatan_dosen,
            'diajukan_at' => $item->created_at?->toIso8601String(),
            'diproses_at' => $item->diproses_at?->toIso8601String(),
            'pemroses' => $item->pemroses?->name,
            'nim' => $item->mahasiswa?->nim,
            'nama' => $item->mahasiswa?->user?->name,
            'status_presensi' => $statusPresensi[$item->pertemuan_id.'-'.$item->mahasiswa_id] ?? null,
            'pertemuan' => [
                'id' => $item->pertemuan?->id,
                'pertemuan_ke' => $item->pertemuan?->pertemuan_ke,
                'tanggal' => $item->pertemuan?->tanggal?->toDateString(),
                'jam_mulai' => $item->pertemuan?->jam_mulai,
                'kode_kelas' => $item->pertemuan?->kelasKuliah?->kode_kelas,
                'nama_matkul' => $item->pertemuan?->kelasKuliah?->mataKuliah?->nama_matkul,
            ],
        ]);

        return Inertia::render('Kelas/PengajuanIzin', [
            'peran' => $this->peran(),
            'status' => $status,
            'pengajuan' => $pengajuan,
        ]);
    }

    /**
     * Setujui (status presensi pertemuan itu menjadi Izin/Sakit) atau tolak pengajuan.
     */
    public function proses(Request $request, PengajuanIzin $pengajuanIzin): RedirectResponse
    {
        $kelas = $pengajuanIzin->pertemuan->kelasKuliah;
        $this->pastikanPengampu($kelas);
        abort_if($this->nilaiTerkunci($kelas), 403, 'Tahun akademik kelas ini sudah tidak aktif.');

        $data = $request->validate([
            'keputusan' => ['required', Rule::in([PengajuanIzin::DISETUJUI, PengajuanIzin::DITOLAK])],
            'catatan_dosen' => ['nullable', 'required_if:keputusan,'.PengajuanIzin::DITOLAK, 'string', 'max:255'],
        ], ['catatan_dosen.required_if' => 'Tuliskan alasan penolakan untuk mahasiswa.'], ['catatan_dosen' => 'Catatan']);

        if ($pengajuanIzin->status !== PengajuanIzin::MENUNGGU) {
            return back()->with('error', 'Pengajuan ini sudah diproses.');
        }

        $sudahHadir = $data['keputusan'] === PengajuanIzin::DISETUJUI && in_array(
            PresensiMahasiswa::where('pertemuan_id', $pengajuanIzin->pertemuan_id)->where('mahasiswa_id', $pengajuanIzin->mahasiswa_id)->value('status'),
            PresensiMahasiswa::DIHITUNG_HADIR,
            true,
        );

        DB::transaction(function () use ($pengajuanIzin, $data, $request, $sudahHadir): void {
            $pengajuanIzin->update([
                'status' => $data['keputusan'],
                // Mahasiswa ternyata hadir: pengajuan tetap tercatat disetujui, tetapi status Hadir tidak diturunkan.
                'catatan_dosen' => $data['catatan_dosen'] ?? ($sudahHadir ? 'Mahasiswa tercatat hadir; status presensi tidak diubah.' : null),
                'diproses_oleh' => $request->user()->id,
                'diproses_at' => now(),
            ]);

            if ($data['keputusan'] === PengajuanIzin::DISETUJUI && ! $sudahHadir) {
                // Pertemuan yang belum dimulai belum punya baris presensi; barisnya dibuat sekarang dan
                // tidak ditimpa saat pertemuan dimulai (siapkanPeserta hanya menambah yang belum ada).
                PresensiMahasiswa::updateOrCreate(
                    ['pertemuan_id' => $pengajuanIzin->pertemuan_id, 'mahasiswa_id' => $pengajuanIzin->mahasiswa_id],
                    [
                        'status' => $pengajuanIzin->jenis,
                        'keterangan' => str($pengajuanIzin->alasan)->limit(250)->toString(),
                        'metode' => 'pengajuan',
                        'waktu_presensi' => null,
                        'diubah_oleh' => $request->user()->id,
                    ],
                );
            }
        });

        return back()->with('success', match (true) {
            $data['keputusan'] === PengajuanIzin::DITOLAK => 'Pengajuan ditolak.',
            $sudahHadir => 'Pengajuan disetujui. Mahasiswa sudah tercatat hadir, jadi status presensinya tetap Hadir.',
            default => 'Pengajuan disetujui; presensi diubah menjadi '.ucfirst($pengajuanIzin->jenis).'.',
        });
    }
}
