<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanPindahKelas;
use App\Models\PengaturanPindahKelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PindahKelasController extends Controller
{
    public function index(Request $request): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;

        abort_if($mahasiswa === null, 403);

        $kelasDiambil = $this->kelasDiambilTahunAktif($mahasiswa);

        $kelasAsal = KelasKuliah::query()
            ->with(['mataKuliah', 'dosen.user', 'tahunAkademik'])
            ->whereIn('id', $kelasDiambil)
            ->orderBy('kode_kelas')
            ->get()
            ->map(fn (KelasKuliah $kelas): array => [
                'id' => $kelas->id,
                'kode_kelas' => $kelas->kode_kelas,
                'matkul_id' => $kelas->matkul_id,
                'mata_kuliah' => $kelas->mataKuliah?->only(['kode_matkul', 'nama_matkul', 'sks']),
                'dosen' => $kelas->dosen?->user?->name,
            ])
            ->values();

        $kelasTujuan = KelasKuliah::query()
            ->with(['mataKuliah', 'dosen.user'])
            ->whereIn('matkul_id', $kelasAsal->pluck('matkul_id')->unique()->all())
            ->whereHas('tahunAkademik', fn ($query) => $query->where('status', true))
            ->whereNotIn('id', $kelasDiambil)
            ->orderBy('kode_kelas')
            ->get()
            ->map(fn (KelasKuliah $kelas): array => [
                'id' => $kelas->id,
                'kode_kelas' => $kelas->kode_kelas,
                'matkul_id' => $kelas->matkul_id,
                'mata_kuliah' => $kelas->mataKuliah?->only(['kode_matkul', 'nama_matkul']),
                'dosen' => $kelas->dosen?->user?->name,
            ])
            ->values();

        $pengajuans = PengajuanPindahKelas::query()
            ->with(['kelasAsal.mataKuliah', 'kelasTujuan.mataKuliah', 'pemroses'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->latest()
            ->get()
            ->map(fn (PengajuanPindahKelas $pengajuan): array => [
                'id' => $pengajuan->id,
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
            ])
            ->values();

        return Inertia::render('Mahasiswa/PindahKelas', [
            'isActive' => PengaturanPindahKelas::current()->is_active,
            'kelasAsal' => $kelasAsal,
            'kelasTujuan' => $kelasTujuan,
            'pengajuans' => $pengajuans,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $mahasiswa = $request->user()->mahasiswaProfile;

        abort_if($mahasiswa === null, 403);
        abort_unless(PengaturanPindahKelas::current()->is_active, 403);

        $kelasDiambil = $this->kelasDiambilTahunAktif($mahasiswa);

        $data = $request->validate([
            'kelas_asal_id' => [
                'required',
                'integer',
                Rule::in($kelasDiambil->all()),
                Rule::unique('pengajuan_pindah_kelas', 'kelas_asal_id')
                    ->where(fn ($query) => $query
                        ->where('mahasiswa_id', $mahasiswa->id)
                        ->where('status', PengajuanPindahKelas::STATUS_PENDING)),
            ],
            'kelas_tujuan_id' => [
                'required',
                'integer',
                'different:kelas_asal_id',
                'exists:kelas_kuliah,id',
            ],
            'alasan' => ['required', 'string', 'max:1000'],
        ], [
            'different' => ':attribute tidak boleh sama dengan kelas asal.',
            'exists' => ':attribute tidak ditemukan.',
            'unique' => 'Anda masih memiliki pengajuan yang menunggu persetujuan untuk kelas asal ini.',
            'integer' => ':attribute tidak valid.',
        ], [
            'kelas_asal_id' => 'kelas asal',
            'kelas_tujuan_id' => 'kelas tujuan',
            'alasan' => 'alasan',
        ]);

        $kelasAsal = KelasKuliah::findOrFail($data['kelas_asal_id']);
        $kelasTujuan = KelasKuliah::findOrFail($data['kelas_tujuan_id']);

        if ($kelasTujuan->matkul_id !== $kelasAsal->matkul_id) {
            throw ValidationException::withMessages([
                'kelas_tujuan_id' => 'Kelas tujuan harus berada pada mata kuliah yang sama dengan kelas asal.',
            ]);
        }

        if ($kelasTujuan->tahun_akademik_id !== $kelasAsal->tahun_akademik_id) {
            throw ValidationException::withMessages([
                'kelas_tujuan_id' => 'Kelas tujuan harus berada pada tahun akademik yang sama dengan kelas asal.',
            ]);
        }

        $mahasiswa->pengajuanPindahKelas()->create([
            'kelas_asal_id' => $kelasAsal->id,
            'kelas_tujuan_id' => $kelasTujuan->id,
            'alasan' => $data['alasan'],
            'status' => PengajuanPindahKelas::STATUS_PENDING,
        ]);

        return back()->with('pindah_kelas_success', 'Pengajuan pindah kelas berhasil dikirim dan menunggu persetujuan admin.');
    }

    /**
     * Kelas di KRS mahasiswa pada tahun akademik aktif: hanya kelas ini yang boleh diajukan pindah.
     *
     * @return Collection<int, int>
     */
    private function kelasDiambilTahunAktif(MahasiswaProfile $mahasiswa): Collection
    {
        return $mahasiswa->krs()
            ->where('status', 'Aktif')
            ->whereHas('kelasKuliah.tahunAkademik', fn ($query) => $query->where('status', true))
            ->pluck('kelas_id');
    }
}
