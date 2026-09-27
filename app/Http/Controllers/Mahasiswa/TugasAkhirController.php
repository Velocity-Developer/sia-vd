<?php

namespace App\Http\Controllers\Mahasiswa;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanAkademik;
use App\Models\RiwayatPengajuanAkademik;
use App\Models\TugasAkhir;
use App\SyaratTugasAkhir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Tugas Akhir & Wisuda mahasiswa: tahap 1 pengajuan TA/Skripsi.
 */
class TugasAkhirController extends Controller
{
    /** Keadaan form satu tahap. */
    public const SELESAI = 'selesai';

    public const MENUNGGU = 'menunggu';

    public const PERBAIKAN = 'perbaikan';

    public const BARU = 'baru';

    public const BELUM_MEMENUHI = 'belum_memenuhi';

    public function index(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $tugasAkhir = TugasAkhir::milik($mahasiswa->id)?->load(['pembimbing1.user:id,name', 'pembimbing2.user:id,name']);
        $pengajuan = PengajuanAkademik::terakhir($mahasiswa->id, PengajuanAkademik::TUGAS_AKHIR);
        $syarat = SyaratTugasAkhir::pengajuanTa($mahasiswa);

        return Inertia::render('Mahasiswa/TugasAkhir', [
            'tugasAkhir' => $tugasAkhir ? [
                'judul' => $tugasAkhir->judul,
                'bidang' => $tugasAkhir->bidang,
                'pembimbing' => $tugasAkhir->namaPembimbing(),
                'status' => $tugasAkhir->status,
                'disahkan_at' => $tugasAkhir->created_at?->toIso8601String(),
            ] : null,
            'pengajuanTa' => [
                'keadaan' => $this->keadaanTa($tugasAkhir, $pengajuan, $syarat),
                'syarat' => $syarat,
                'pengajuan' => $pengajuan ? $this->tampilkan($pengajuan) : null,
            ],
            'riwayat' => PengajuanAkademik::query()->where('mahasiswa_id', $mahasiswa->id)
                ->with('riwayat.pengguna:id,name')->latest('id')->get()
                ->map(fn (PengajuanAkademik $p): array => [
                    'id' => $p->id,
                    'jenis' => $p->jenis,
                    'status' => $p->status,
                    'diajukan_at' => $p->diajukan_at?->toIso8601String(),
                    'riwayat' => $p->riwayat->map(fn (RiwayatPengajuanAkademik $r): array => [
                        'status' => $r->status,
                        'catatan' => $r->catatan,
                        'oleh' => $r->pengguna?->name,
                        'waktu' => $r->created_at?->toIso8601String(),
                    ]),
                ]),
            'dosenOptions' => DosenProfile::opsi(),
        ]);
    }

    /**
     * Kirim pengajuan TA baru, atau kirim ulang pengajuan yang diminta perbaikan.
     */
    public function ajukanTa(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);

        return DB::transaction(function () use ($request, $mahasiswa): RedirectResponse {
            // Kunci baris mahasiswa agar dua kiriman bersamaan tidak membuat dua pengajuan.
            MahasiswaProfile::query()->whereKey($mahasiswa->id)->lockForUpdate()->first();

            $pengajuan = PengajuanAkademik::terakhir($mahasiswa->id, PengajuanAkademik::TUGAS_AKHIR);
            $keadaan = $this->keadaanTa(TugasAkhir::milik($mahasiswa->id), $pengajuan, SyaratTugasAkhir::pengajuanTa($mahasiswa));
            $perbaikan = $keadaan === self::PERBAIKAN;

            $pesan = match ($keadaan) {
                self::SELESAI => 'Tugas akhir Anda sudah disahkan.',
                self::MENUNGGU => 'Pengajuan Anda masih menunggu diproses admin.',
                self::BELUM_MEMENUHI => 'Anda belum memenuhi syarat pengajuan tugas akhir.',
                default => null,
            };
            if ($pesan !== null) {
                throw ValidationException::withMessages(['judul' => $pesan]);
            }

            $data = $request->validate([
                'judul' => ['required', 'string', 'max:300'],
                'bidang' => ['required', 'string', 'max:150'],
                'ringkasan' => ['required', 'string', 'max:5000'],
                'usulan_pembimbing_1_id' => ['required', 'integer', 'exists:dosen_profiles,id'],
                'usulan_pembimbing_2_id' => ['nullable', 'integer', 'exists:dosen_profiles,id', 'different:usulan_pembimbing_1_id'],
                'proposal' => [$perbaikan ? 'nullable' : 'required', 'file', 'max:10240', 'extensions:pdf', 'mimes:pdf'],
            ], [
                'proposal.extensions' => 'Proposal harus berupa PDF.',
                'proposal.mimes' => 'Isi berkas proposal bukan PDF.',
                'proposal.max' => 'Ukuran proposal maksimal 10 MB.',
                'usulan_pembimbing_2_id.different' => 'Usulan pembimbing 2 harus berbeda dari pembimbing 1.',
            ], [
                'judul' => 'Judul',
                'bidang' => 'Bidang',
                'ringkasan' => 'Ringkasan proposal',
                'usulan_pembimbing_1_id' => 'Usulan pembimbing 1',
                'usulan_pembimbing_2_id' => 'Usulan pembimbing 2',
                'proposal' => 'Proposal',
            ]);

            $isian = collect($data)->except('proposal')->all();
            $lampiran = $perbaikan ? $pengajuan->lampiran : [];
            if ($request->file('proposal') instanceof UploadedFile) {
                if ($perbaikan && isset($lampiran['proposal'])) {
                    Storage::disk(AllowedUpload::DISK)->delete($lampiran['proposal']);
                }
                $lampiran['proposal'] = $this->simpanBerkas($request->file('proposal'));
            }

            if ($perbaikan) {
                $pengajuan->update(['isian' => $isian, 'lampiran' => $lampiran]);
            } else {
                $pengajuan = PengajuanAkademik::query()->create([
                    'mahasiswa_id' => $mahasiswa->id,
                    'jenis' => PengajuanAkademik::TUGAS_AKHIR,
                    'isian' => $isian,
                    'lampiran' => $lampiran,
                ]);
            }
            $pengajuan->catat(PengajuanAkademik::MENUNGGU, null, $request->user()->id);

            return back()->with('success', $perbaikan
                ? 'Perbaikan pengajuan tugas akhir terkirim dan menunggu diproses admin.'
                : 'Pengajuan tugas akhir terkirim dan menunggu diproses admin.');
        });
    }

    /**
     * @param  list<array{terpenuhi: bool}>  $syarat
     */
    private function keadaanTa(?TugasAkhir $tugasAkhir, ?PengajuanAkademik $pengajuan, array $syarat): string
    {
        return match (true) {
            $tugasAkhir !== null => self::SELESAI,
            $pengajuan?->status === PengajuanAkademik::MENUNGGU => self::MENUNGGU,
            ! SyaratTugasAkhir::terpenuhi($syarat) => self::BELUM_MEMENUHI,
            $pengajuan?->status === PengajuanAkademik::PERLU_PERBAIKAN => self::PERBAIKAN,
            default => self::BARU,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function tampilkan(PengajuanAkademik $pengajuan): array
    {
        return [
            'id' => $pengajuan->id,
            'status' => $pengajuan->status,
            'isian' => $pengajuan->isian,
            'lampiran' => array_keys($pengajuan->lampiran ?? []),
            'catatan' => $pengajuan->catatan,
            'diajukan_at' => $pengajuan->diajukan_at?->toIso8601String(),
            'diproses_at' => $pengajuan->diproses_at?->toIso8601String(),
        ];
    }

    private function simpanBerkas(UploadedFile $berkas): string
    {
        return $berkas->storeAs('pengajuan-akademik', Str::random(24).'.'.strtolower($berkas->getClientOriginalExtension()), AllowedUpload::DISK);
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }
}
