<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Concerns\KirimPengajuanAkademik;
use App\Http\Controllers\Controller;
use App\Models\JenisBiaya;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanAkademik;
use App\Models\PengaturanAkademik;
use App\Models\RiwayatPengajuanAkademik;
use App\Models\TahunAkademik;
use App\PengajuanCuti;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Pengajuan Cuti mahasiswa: ajukan cuti satu semester, atau ajukan aktif kembali saat berstatus Cuti.
 */
class PengajuanCutiController extends Controller
{
    use KirimPengajuanAkademik;

    public const MENUNGGU = 'menunggu';

    public const PERBAIKAN = 'perbaikan';

    public const BARU = 'baru';

    public const BELUM_MEMENUHI = 'belum_memenuhi';

    private const EKSTENSI_DOKUMEN = 'pdf,jpg,jpeg,png';

    public function index(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $cuti = PengajuanAkademik::terakhir($mahasiswa->id, PengajuanAkademik::CUTI);
        $aktifKembali = PengajuanAkademik::terakhir($mahasiswa->id, PengajuanAkademik::AKTIF_KEMBALI);
        $namaTahun = TahunAkademik::query()->get()->mapWithKeys(fn (TahunAkademik $t): array => [$t->id => $t->label()]);

        return Inertia::render('Mahasiswa/PengajuanCuti', [
            'mahasiswa' => [
                'status' => $mahasiswa->status,
                'jumlah_cuti' => PengajuanCuti::disetujui($mahasiswa->id)->count(),
                'maks_cuti' => PengaturanAkademik::current()->maks_cuti,
            ],
            'cuti' => [
                'keadaan' => $this->keadaanCuti($mahasiswa, $cuti),
                'alasan' => PengajuanCuti::alasanTidakBolehCuti($mahasiswa),
                'pengajuan' => $cuti ? $this->tampilkan($cuti, $namaTahun->all()) : null,
                'tahunOptions' => PengajuanCuti::tahunDibuka($mahasiswa->id)->map(fn (TahunAkademik $t): array => [
                    'id' => $t->id,
                    'nama' => $t->label(),
                    'aktif' => $t->status,
                    'batas' => $t->tanggal_cuti_akhir?->toDateString(),
                ])->values(),
            ],
            'aktifKembali' => [
                'keadaan' => $this->keadaanAktifKembali($mahasiswa, $aktifKembali),
                'pengajuan' => $aktifKembali ? $this->tampilkan($aktifKembali, $namaTahun->all()) : null,
            ],
            'biaya' => JenisBiaya::infoUntuk($mahasiswa, JenisBiaya::CUTI),
            'riwayat' => PengajuanAkademik::query()->where('mahasiswa_id', $mahasiswa->id)->whereIn('jenis', PengajuanAkademik::JENIS_CUTI)
                ->with('riwayat.pengguna:id,name')->latest('id')->get()
                ->map(fn (PengajuanAkademik $p): array => [
                    'id' => $p->id,
                    'jenis' => $p->jenis,
                    'status' => $p->status,
                    'tahun_akademik' => $namaTahun[$p->isian['tahun_akademik_id'] ?? 0] ?? null,
                    'riwayat' => $p->riwayat->map(fn (RiwayatPengajuanAkademik $r): array => [
                        'status' => $r->status,
                        'catatan' => $r->catatan,
                        'oleh' => $r->pengguna?->name,
                        'waktu' => $r->created_at?->toIso8601String(),
                    ]),
                ]),
        ]);
    }

    /**
     * Kirim pengajuan cuti baru, atau kirim ulang yang diminta perbaikan.
     */
    public function ajukan(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);

        return $this->simpan($request, $mahasiswa, PengajuanAkademik::CUTI, fn (?PengajuanAkademik $p): string => $this->keadaanCuti($mahasiswa->refresh(), $p),
            fn (bool $perbaikan): array => [
                'tahun_akademik_id' => ['required', 'integer', function (string $atribut, mixed $nilai, \Closure $gagal) use ($mahasiswa): void {
                    if (! PengajuanCuti::tahunDibuka($mahasiswa->id)->contains('id', (int) $nilai)) {
                        $gagal('Periode pengajuan cuti untuk semester ini tidak dibuka.');
                    }
                }],
                'alasan' => ['required', 'string', 'max:2000'],
                'dokumen_pendukung' => ['nullable', 'file', 'max:5120', 'extensions:'.self::EKSTENSI_DOKUMEN, 'mimes:'.self::EKSTENSI_DOKUMEN],
            ], [
                '*.extensions' => ':attribute harus berupa PDF atau foto (JPG/PNG).',
                '*.max' => 'Ukuran :attribute maksimal 5 MB.',
            ], [
                'tahun_akademik_id' => 'Semester cuti',
                'alasan' => 'Alasan cuti',
                'dokumen_pendukung' => 'Dokumen pendukung',
            ], [
                self::MENUNGGU => 'Pengajuan cuti Anda masih menunggu diproses admin.',
                self::BELUM_MEMENUHI => PengajuanCuti::alasanTidakBolehCuti($mahasiswa) ?? 'Anda belum dapat mengajukan cuti.',
            ], 'cuti', 'tahun_akademik_id');
    }

    /**
     * Mahasiswa Cuti meminta status Aktif kembali.
     */
    public function ajukanAktifKembali(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);

        return $this->simpan($request, $mahasiswa, PengajuanAkademik::AKTIF_KEMBALI, fn (?PengajuanAkademik $p): string => $this->keadaanAktifKembali($mahasiswa->refresh(), $p),
            fn (bool $perbaikan): array => ['keterangan' => ['nullable', 'string', 'max:1000']],
            [], ['keterangan' => 'Keterangan'], [
                self::MENUNGGU => 'Pengajuan aktif kembali Anda masih menunggu diproses admin.',
                self::BELUM_MEMENUHI => 'Pengajuan aktif kembali hanya untuk mahasiswa berstatus Cuti.',
            ], 'aktif kembali', 'keterangan');
    }

    private function keadaanCuti(MahasiswaProfile $mahasiswa, ?PengajuanAkademik $pengajuan): string
    {
        return match (true) {
            $pengajuan?->sedangDiproses() === true => self::MENUNGGU,
            PengajuanCuti::alasanTidakBolehCuti($mahasiswa) !== null => self::BELUM_MEMENUHI,
            $pengajuan?->status === PengajuanAkademik::PERLU_PERBAIKAN => self::PERBAIKAN,
            default => self::BARU,
        };
    }

    private function keadaanAktifKembali(MahasiswaProfile $mahasiswa, ?PengajuanAkademik $pengajuan): string
    {
        return match (true) {
            $pengajuan?->sedangDiproses() === true => self::MENUNGGU,
            PengajuanCuti::alasanTidakBolehAktifKembali($mahasiswa) !== null => self::BELUM_MEMENUHI,
            $pengajuan?->status === PengajuanAkademik::PERLU_PERBAIKAN => self::PERBAIKAN,
            default => self::BARU,
        };
    }

    /**
     * @param  array<int, string>  $namaTahun
     * @return array<string, mixed>
     */
    private function tampilkan(PengajuanAkademik $pengajuan, array $namaTahun): array
    {
        return [
            'id' => $pengajuan->id,
            'status' => $pengajuan->status,
            'isian' => $pengajuan->isian,
            'tahun_akademik' => $namaTahun[$pengajuan->isian['tahun_akademik_id'] ?? 0] ?? null,
            'lampiran' => array_keys($pengajuan->lampiran ?? []),
            'catatan' => $pengajuan->catatan,
            'diajukan_at' => $pengajuan->diajukan_at?->toIso8601String(),
            'diproses_at' => $pengajuan->diproses_at?->toIso8601String(),
        ];
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }
}
