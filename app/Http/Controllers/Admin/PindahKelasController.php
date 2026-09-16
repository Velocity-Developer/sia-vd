<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Krs;
use App\Models\PengajuanPindahKelas;
use App\Models\PengaturanPindahKelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PindahKelasController extends Controller
{
    public function index(): Response
    {
        $pengajuans = PengajuanPindahKelas::query()
            ->with([
                'mahasiswa.user:id,name',
                'mahasiswa.prodi:id,nama_prodi',
                'mahasiswa.krs:id,mahasiswa_id,kelas_id,nilai',
                'kelasAsal.mataKuliah:id,kode_matkul,nama_matkul',
                'kelasTujuan.mataKuliah:id,kode_matkul,nama_matkul',
                'pemroses:id,name',
            ])
            ->latest()
            ->paginate(10)
            ->through(function (PengajuanPindahKelas $pengajuan): array {
                $nilai = $pengajuan->mahasiswa?->krs
                    ->firstWhere('kelas_id', $pengajuan->kelas_asal_id)?->nilai;

                return [
                    'id' => $pengajuan->id,
                    'mahasiswa' => $pengajuan->mahasiswa?->user?->name,
                    'nim' => $pengajuan->mahasiswa?->nim,
                    'prodi' => $pengajuan->mahasiswa?->prodi?->nama_prodi,
                    'kelas_asal' => $pengajuan->kelasAsal?->kode_kelas,
                    'kelas_asal_matkul' => $pengajuan->kelasAsal?->mataKuliah?->nama_matkul,
                    'kelas_tujuan' => $pengajuan->kelasTujuan?->kode_kelas,
                    'kelas_tujuan_matkul' => $pengajuan->kelasTujuan?->mataKuliah?->nama_matkul,
                    'alasan' => $pengajuan->alasan,
                    'status' => $pengajuan->status,
                    'catatan_admin' => $pengajuan->catatan_admin,
                    'diproses_oleh' => $pengajuan->pemroses?->name,
                    'diproses_at' => $pengajuan->diproses_at?->toIso8601String(),
                    'created_at' => $pengajuan->created_at?->toIso8601String(),
                    'nilai' => $nilai,
                    'nilai_terisi' => filled($nilai),
                ];
            });

        return Inertia::render('Admin/PindahKelas', [
            'isActive' => PengaturanPindahKelas::current()->is_active,
            'pengajuans' => $pengajuans,
        ]);
    }

    public function updateSetting(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ], ['required' => ':attribute wajib diisi.', 'boolean' => ':attribute tidak valid.'], [
            'is_active' => 'status form pindah kelas',
        ]);

        PengaturanPindahKelas::current()->update([
            'is_active' => $data['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', $data['is_active']
            ? 'Form pindah kelas berhasil dibuka.'
            : 'Form pindah kelas berhasil ditutup.');
    }

    /**
     * Setujui pengajuan lalu pindahkan mahasiswa dengan mengubah kelas_id pada baris KRS kelas asal.
     * Kapasitas kelas tujuan sengaja tidak divalidasi karena approve adalah keputusan manual admin.
     */
    public function approve(Request $request, PengajuanPindahKelas $pengajuan): RedirectResponse
    {
        abort_unless($pengajuan->status === PengajuanPindahKelas::STATUS_PENDING, 404);

        $krs = Krs::query()
            ->where('mahasiswa_id', $pengajuan->mahasiswa_id)
            ->where('kelas_id', $pengajuan->kelas_asal_id)
            ->first();

        if ($krs === null) {
            return back()->with('error', 'Baris KRS pada kelas asal tidak ditemukan, pengajuan tidak dapat diproses.');
        }

        if (! $request->boolean('force') && filled($krs->nilai)) {
            return back()->with('pindah_kelas_warning', "Mahasiswa ini sudah memiliki nilai {$krs->nilai} pada kelas asal. Menyetujui pengajuan akan memindahkan baris KRS tersebut beserta nilainya ke kelas tujuan.");
        }

        $sudahTerdaftarDiTujuan = Krs::query()
            ->where('mahasiswa_id', $pengajuan->mahasiswa_id)
            ->where('kelas_id', $pengajuan->kelas_tujuan_id)
            ->exists();

        if ($sudahTerdaftarDiTujuan) {
            return back()->with('error', 'Mahasiswa sudah terdaftar pada kelas tujuan, pengajuan tidak dapat disetujui.');
        }

        DB::transaction(function () use ($request, $pengajuan, $krs): void {
            $krs->update(['kelas_id' => $pengajuan->kelas_tujuan_id]);

            $pengajuan->update([
                'status' => PengajuanPindahKelas::STATUS_DISETUJUI,
                'diproses_oleh' => $request->user()->id,
                'diproses_at' => now(),
            ]);
        });

        return back()->with('success', 'Pengajuan pindah kelas disetujui dan mahasiswa dipindahkan ke kelas tujuan.');
    }

    public function reject(Request $request, PengajuanPindahKelas $pengajuan): RedirectResponse
    {
        abort_unless($pengajuan->status === PengajuanPindahKelas::STATUS_PENDING, 404);

        $data = $request->validate([
            'catatan_admin' => ['required', 'string', 'max:1000'],
        ], [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute maksimal :max karakter.',
        ], [
            'catatan_admin' => 'catatan alasan penolakan',
        ]);

        $pengajuan->update([
            'status' => PengajuanPindahKelas::STATUS_DITOLAK,
            'catatan_admin' => $data['catatan_admin'],
            'diproses_oleh' => $request->user()->id,
            'diproses_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan pindah kelas ditolak.');
    }
}
