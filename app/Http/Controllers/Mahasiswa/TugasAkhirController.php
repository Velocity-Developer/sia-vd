<?php

namespace App\Http\Controllers\Mahasiswa;

use App\AllowedUpload;
use App\Http\Controllers\Concerns\KirimPengajuanAkademik;
use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\JenisBiaya;
use App\Models\MahasiswaProfile;
use App\Models\Pendadaran;
use App\Models\PengajuanAkademik;
use App\Models\PeriodeWisuda;
use App\Models\RiwayatPengajuanAkademik;
use App\Models\TugasAkhir;
use App\Models\Wisuda;
use App\SyaratTugasAkhir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Tugas Akhir & Wisuda mahasiswa: tahap 1 pengajuan TA/Skripsi, tahap 2 pendaftaran pendadaran.
 */
class TugasAkhirController extends Controller
{
    use KirimPengajuanAkademik;

    /** Keadaan form satu tahap. */
    public const TERKUNCI = 'terkunci';

    public const SELESAI = 'selesai';

    public const TERJADWAL = 'terjadwal';

    public const TERDAFTAR = 'terdaftar';

    public const MENUNGGU = 'menunggu';

    public const PERBAIKAN = 'perbaikan';

    public const BARU = 'baru';

    public const BELUM_MEMENUHI = 'belum_memenuhi';

    private const EKSTENSI_DOKUMEN = 'pdf,jpg,jpeg,png';

    public const UKURAN_TOGA = ['S', 'M', 'L', 'XL', 'XXL'];

    public function index(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $tugasAkhir = TugasAkhir::milik($mahasiswa->id)?->load(['pembimbing1.user:id,name', 'pembimbing2.user:id,name']);
        $pengajuanTa = PengajuanAkademik::terakhir($mahasiswa->id, PengajuanAkademik::TUGAS_AKHIR);
        $pengajuanPendadaran = PengajuanAkademik::terakhir($mahasiswa->id, PengajuanAkademik::PENDADARAN);
        $syaratTa = SyaratTugasAkhir::pengajuanTa($mahasiswa);
        $syaratPendadaran = $tugasAkhir ? SyaratTugasAkhir::pendadaran($mahasiswa) : [];
        $pendadaran = $this->pendadaranAktif($tugasAkhir)?->load(['ruang', 'penguji1.user:id,name', 'penguji2.user:id,name', 'penguji3.user:id,name']);
        $pengajuanWisuda = PengajuanAkademik::terakhir($mahasiswa->id, PengajuanAkademik::WISUDA);
        $wisuda = Wisuda::query()->where('mahasiswa_id', $mahasiswa->id)->with('periode')->first();
        $syaratWisuda = $tugasAkhir?->status === TugasAkhir::SELESAI ? SyaratTugasAkhir::wisuda($mahasiswa) : [];
        $mahasiswa->loadMissing('user:id,name');

        return Inertia::render('Mahasiswa/TugasAkhir', [
            'tugasAkhir' => $tugasAkhir ? [
                'judul' => $tugasAkhir->judul,
                'bidang' => $tugasAkhir->bidang,
                'pembimbing' => $tugasAkhir->namaPembimbing(),
                'status' => $tugasAkhir->status,
                'disahkan_at' => $tugasAkhir->created_at?->toIso8601String(),
            ] : null,
            'pengajuanTa' => [
                'keadaan' => $this->keadaanTa($tugasAkhir, $pengajuanTa, $syaratTa),
                'syarat' => $syaratTa,
                'pengajuan' => $pengajuanTa ? $this->tampilkan($pengajuanTa) : null,
            ],
            'pendaftaranPendadaran' => [
                'keadaan' => $this->keadaanPendadaran($tugasAkhir, $pendadaran, $pengajuanPendadaran, $syaratPendadaran),
                'syarat' => $syaratPendadaran,
                'pengajuan' => $pengajuanPendadaran ? $this->tampilkan($pengajuanPendadaran) : null,
                'jadwal' => $pendadaran?->jadwal(),
                // Hasil pendadaran terakhir (yang aktif, atau yang sudah selesai/tidak lulus).
                'hasil' => ($terakhir = $this->pendadaranTerakhir($tugasAkhir)) && $terakhir->hasil !== null ? [
                    'id' => $terakhir->id,
                    'tanggal' => $terakhir->tanggal->toDateString(),
                    ...$terakhir->ringkasanHasil(),
                ] : null,
            ],
            'pendaftaranWisuda' => [
                'keadaan' => $this->keadaanWisuda($tugasAkhir, $wisuda, $pengajuanWisuda, $syaratWisuda),
                'syarat' => $syaratWisuda,
                'pengajuan' => $pengajuanWisuda ? $this->tampilkan($pengajuanWisuda) : null,
                'periodeOptions' => PeriodeWisuda::query()->dibuka()->orderBy('tanggal_acara')->get()
                    ->filter(fn (PeriodeWisuda $p): bool => $p->bisaDidaftar())->map(fn (PeriodeWisuda $p): array => $p->ringkas())->values(),
                // Data ijazah dari profil, untuk dikonfirmasi (atau dikoreksi) di form.
                'dataIjazah' => [
                    'nama_ijazah' => $mahasiswa->user?->name,
                    'tempat_lahir' => $mahasiswa->tempat_lahir,
                    'tanggal_lahir' => $mahasiswa->tanggal_lahir?->toDateString(),
                ],
                'ukuranToga' => self::UKURAN_TOGA,
                'wisuda' => $wisuda ? [
                    'id' => $wisuda->id,
                    'periode' => $wisuda->periode?->ringkas(),
                    'nomor_skl' => $wisuda->nomor_skl,
                    'skl_terbit_at' => $wisuda->skl_terbit_at?->toIso8601String(),
                    'tanggal_lulus' => $wisuda->tanggal_lulus?->toDateString(),
                    'ipk' => $wisuda->ipk,
                    'predikat' => $wisuda->predikat,
                ] : null,
            ],
            'riwayat' => PengajuanAkademik::query()->where('mahasiswa_id', $mahasiswa->id)->whereIn('jenis', PengajuanAkademik::JENIS)
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
            'biaya' => [
                'pendadaran' => JenisBiaya::infoUntuk($mahasiswa, JenisBiaya::PENDADARAN),
                'wisuda' => JenisBiaya::infoUntuk($mahasiswa, JenisBiaya::WISUDA),
            ],
        ]);
    }

    /**
     * Kirim pengajuan TA baru, atau kirim ulang pengajuan yang diminta perbaikan.
     */
    public function ajukanTa(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);

        return $this->simpan($request, $mahasiswa, PengajuanAkademik::TUGAS_AKHIR, function (?PengajuanAkademik $pengajuan) use ($mahasiswa): string {
            return $this->keadaanTa(TugasAkhir::milik($mahasiswa->id), $pengajuan, SyaratTugasAkhir::pengajuanTa($mahasiswa));
        }, fn (bool $perbaikan): array => [
            'judul' => ['required', 'string', 'max:300'],
            'bidang' => ['required', 'string', 'max:150'],
            'ringkasan' => ['required', 'string', 'max:5000'],
            'usulan_pembimbing_1_id' => ['required', 'integer', DosenProfile::rulePilihan()],
            'usulan_pembimbing_2_id' => ['nullable', 'integer', DosenProfile::rulePilihan(), 'different:usulan_pembimbing_1_id'],
            'proposal' => [$perbaikan ? 'nullable' : 'required', 'file', 'max:10240', 'extensions:pdf', 'mimes:pdf'],
        ], [
            'proposal.extensions' => 'Proposal harus berupa PDF.',
            'proposal.max' => 'Ukuran proposal maksimal 10 MB.',
            'usulan_pembimbing_2_id.different' => 'Usulan pembimbing 2 harus berbeda dari pembimbing 1.',
        ], [
            'judul' => 'Judul',
            'bidang' => 'Bidang',
            'ringkasan' => 'Ringkasan proposal',
            'usulan_pembimbing_1_id' => 'Usulan pembimbing 1',
            'usulan_pembimbing_2_id' => 'Usulan pembimbing 2',
            'proposal' => 'Proposal',
        ], [
            self::SELESAI => 'Tugas akhir Anda sudah disahkan.',
            self::MENUNGGU => 'Pengajuan Anda masih menunggu diproses admin.',
            self::BELUM_MEMENUHI => 'Anda belum memenuhi syarat pengajuan tugas akhir.',
        ], 'tugas akhir');
    }

    /**
     * Daftar pendadaran (atau kirim ulang perbaikannya) dengan naskah, lembar persetujuan, dan bukti bayar.
     */
    public function ajukanPendadaran(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);

        return $this->simpan($request, $mahasiswa, PengajuanAkademik::PENDADARAN, function (?PengajuanAkademik $pengajuan) use ($mahasiswa): string {
            $tugasAkhir = TugasAkhir::milik($mahasiswa->id);

            return $this->keadaanPendadaran($tugasAkhir, $this->pendadaranAktif($tugasAkhir), $pengajuan, $tugasAkhir ? SyaratTugasAkhir::pendadaran($mahasiswa) : []);
        }, fn (bool $perbaikan): array => [
            'judul' => ['required', 'string', 'max:300'],
            'naskah' => [$perbaikan ? 'nullable' : 'required', 'file', 'max:20480', 'extensions:pdf', 'mimes:pdf'],
            'persetujuan_pembimbing' => [$perbaikan ? 'nullable' : 'required', 'file', 'max:5120', 'extensions:'.self::EKSTENSI_DOKUMEN, 'mimes:'.self::EKSTENSI_DOKUMEN],
            'bukti_bayar' => [$perbaikan ? 'nullable' : 'required', 'file', 'max:5120', 'extensions:'.self::EKSTENSI_DOKUMEN, 'mimes:'.self::EKSTENSI_DOKUMEN],
        ], [
            'naskah.extensions' => 'Naskah harus berupa PDF.',
            'naskah.max' => 'Ukuran naskah maksimal 20 MB.',
            '*.extensions' => ':attribute harus berupa PDF atau foto (JPG/PNG).',
            '*.max' => 'Ukuran :attribute maksimal 5 MB.',
        ], [
            'judul' => 'Judul final',
            'naskah' => 'Naskah',
            'persetujuan_pembimbing' => 'Lembar persetujuan pembimbing',
            'bukti_bayar' => 'Bukti bayar pendadaran',
        ], [
            self::TERKUNCI => 'Tugas akhir Anda belum disahkan.',
            self::TERJADWAL => 'Pendadaran Anda sudah dijadwalkan.',
            self::SELESAI => 'Pendadaran Anda sudah selesai.',
            self::MENUNGGU => 'Pendaftaran Anda masih menunggu diproses.',
            self::BELUM_MEMENUHI => 'Anda belum memenuhi syarat pendaftaran pendadaran.',
        ], 'pendaftaran pendadaran');
    }

    /**
     * Daftar wisuda: pilih periode, konfirmasi data ijazah, ukuran toga, dan unggah berkas (termasuk bukti bayar).
     */
    public function ajukanWisuda(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);

        return $this->simpan($request, $mahasiswa, PengajuanAkademik::WISUDA, function (?PengajuanAkademik $pengajuan) use ($mahasiswa): string {
            $tugasAkhir = TugasAkhir::milik($mahasiswa->id);
            $wisuda = Wisuda::query()->where('mahasiswa_id', $mahasiswa->id)->first();

            return $this->keadaanWisuda($tugasAkhir, $wisuda, $pengajuan, $tugasAkhir?->status === TugasAkhir::SELESAI ? SyaratTugasAkhir::wisuda($mahasiswa) : []);
        }, fn (bool $perbaikan): array => [
            'periode_wisuda_id' => ['required', 'integer', function (string $atribut, mixed $nilai, \Closure $gagal): void {
                if (! PeriodeWisuda::query()->find($nilai)?->bisaDidaftar()) {
                    $gagal('Periode wisuda ini sudah ditutup atau kuotanya penuh.');
                }
            }],
            'nama_ijazah' => ['required', 'string', 'max:150'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date_format:Y-m-d', 'before:today'],
            'ukuran_toga' => ['required', Rule::in(self::UKURAN_TOGA)],
            'pas_foto' => [$perbaikan ? 'nullable' : 'required', 'file', 'max:2048', 'extensions:jpg,jpeg,png', 'mimes:jpg,jpeg,png'],
            'naskah_final' => [$perbaikan ? 'nullable' : 'required', 'file', 'max:20480', 'extensions:pdf', 'mimes:pdf'],
            'bebas_pinjam' => [$perbaikan ? 'nullable' : 'required', 'file', 'max:5120', 'extensions:'.self::EKSTENSI_DOKUMEN, 'mimes:'.self::EKSTENSI_DOKUMEN],
            'bukti_bayar' => [$perbaikan ? 'nullable' : 'required', 'file', 'max:5120', 'extensions:'.self::EKSTENSI_DOKUMEN, 'mimes:'.self::EKSTENSI_DOKUMEN],
        ], [
            'pas_foto.extensions' => 'Pas foto harus berupa JPG atau PNG.',
            'pas_foto.max' => 'Ukuran pas foto maksimal 2 MB.',
            'naskah_final.extensions' => 'Naskah final harus berupa PDF.',
            'naskah_final.max' => 'Ukuran naskah final maksimal 20 MB.',
            '*.extensions' => ':attribute harus berupa PDF atau foto (JPG/PNG).',
            '*.max' => 'Ukuran :attribute maksimal 5 MB.',
        ], [
            'periode_wisuda_id' => 'Periode wisuda',
            'nama_ijazah' => 'Nama di ijazah',
            'tempat_lahir' => 'Tempat lahir',
            'tanggal_lahir' => 'Tanggal lahir',
            'ukuran_toga' => 'Ukuran toga',
            'pas_foto' => 'Pas foto',
            'naskah_final' => 'Naskah final',
            'bebas_pinjam' => 'Bukti bebas pinjam perpustakaan',
            'bukti_bayar' => 'Bukti bayar wisuda',
        ], [
            self::TERKUNCI => 'Anda belum lulus pendadaran.',
            self::TERDAFTAR => 'Anda sudah terdaftar sebagai peserta wisuda.',
            self::MENUNGGU => 'Pendaftaran Anda masih menunggu diproses admin.',
            self::BELUM_MEMENUHI => 'Anda belum memenuhi syarat pendaftaran wisuda.',
        ], 'pendaftaran wisuda');
    }

    /**
     * @param  list<array{terpenuhi: bool}>  $syarat
     */
    private function keadaanTa(?TugasAkhir $tugasAkhir, ?PengajuanAkademik $pengajuan, array $syarat): string
    {
        return match (true) {
            $tugasAkhir !== null => self::SELESAI,
            $pengajuan?->sedangDiproses() === true => self::MENUNGGU,
            ! SyaratTugasAkhir::terpenuhi($syarat) => self::BELUM_MEMENUHI,
            $pengajuan?->status === PengajuanAkademik::PERLU_PERBAIKAN => self::PERBAIKAN,
            default => self::BARU,
        };
    }

    /**
     * @param  list<array{terpenuhi: bool}>  $syarat
     */
    private function keadaanPendadaran(?TugasAkhir $tugasAkhir, ?Pendadaran $pendadaran, ?PengajuanAkademik $pengajuan, array $syarat): string
    {
        return match (true) {
            $tugasAkhir === null => self::TERKUNCI,
            $tugasAkhir->status === TugasAkhir::SELESAI => self::SELESAI,
            $pendadaran !== null => self::TERJADWAL,
            $pengajuan?->sedangDiproses() === true => self::MENUNGGU,
            ! SyaratTugasAkhir::terpenuhi($syarat) => self::BELUM_MEMENUHI,
            $pengajuan?->status === PengajuanAkademik::PERLU_PERBAIKAN => self::PERBAIKAN,
            default => self::BARU,
        };
    }

    /**
     * Unggah naskah revisi setelah pendadaran lulus dengan revisi; ketua penguji mengesahkannya.
     */
    public function unggahRevisi(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        $request->validate(
            ['naskah_revisi' => ['required', 'file', 'max:20480', 'extensions:pdf', 'mimes:pdf']],
            ['naskah_revisi.extensions' => 'Naskah revisi harus berupa PDF.', 'naskah_revisi.mimes' => 'Isi berkas naskah revisi bukan PDF.', 'naskah_revisi.max' => 'Ukuran naskah revisi maksimal 20 MB.'],
            ['naskah_revisi' => 'Naskah revisi'],
        );

        $pendadaran = $this->pendadaranAktif(TugasAkhir::milik($mahasiswa->id));
        if ($pendadaran?->status !== Pendadaran::REVISI) {
            return back()->with('error', 'Tidak ada revisi pendadaran yang perlu diunggah.');
        }
        if ($pendadaran->revisi_diunggah_at !== null) {
            return back()->with('error', 'Naskah revisi sedang menunggu pengesahan ketua penguji.');
        }

        if ($pendadaran->naskah_revisi !== null) {
            Storage::disk(AllowedUpload::DISK)->delete($pendadaran->naskah_revisi);
        }
        $pendadaran->update([
            'naskah_revisi' => $request->file('naskah_revisi')->storeAs('pengajuan-akademik', Str::random(24).'.pdf', AllowedUpload::DISK),
            'revisi_diunggah_at' => now(),
            'catatan_revisi' => null,
        ]);

        return back()->with('success', 'Naskah revisi terkirim dan menunggu pengesahan ketua penguji.');
    }

    /**
     * @param  list<array{terpenuhi: bool}>  $syarat
     */
    private function keadaanWisuda(?TugasAkhir $tugasAkhir, ?Wisuda $wisuda, ?PengajuanAkademik $pengajuan, array $syarat): string
    {
        return match (true) {
            $tugasAkhir?->status !== TugasAkhir::SELESAI => self::TERKUNCI,
            $wisuda !== null => self::TERDAFTAR,
            $pengajuan?->sedangDiproses() === true => self::MENUNGGU,
            ! SyaratTugasAkhir::terpenuhi($syarat) => self::BELUM_MEMENUHI,
            $pengajuan?->status === PengajuanAkademik::PERLU_PERBAIKAN => self::PERBAIKAN,
            default => self::BARU,
        };
    }

    private function pendadaranTerakhir(?TugasAkhir $tugasAkhir): ?Pendadaran
    {
        return $tugasAkhir === null ? null : Pendadaran::query()->where('tugas_akhir_id', $tugasAkhir->id)->latest('id')->first();
    }

    private function pendadaranAktif(?TugasAkhir $tugasAkhir): ?Pendadaran
    {
        return $tugasAkhir === null ? null
            : Pendadaran::query()->where('tugas_akhir_id', $tugasAkhir->id)->whereIn('status', Pendadaran::AKTIF)->latest('id')->first();
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

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }
}
