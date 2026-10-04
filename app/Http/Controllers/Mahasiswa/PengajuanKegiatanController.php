<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Feature;
use App\Http\Controllers\Concerns\KirimPengajuanAkademik;
use App\Http\Controllers\Controller;
use App\Models\GelombangKompre;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanAkademik;
use App\Models\RiwayatPengajuanAkademik;
use App\Models\TugasAkhir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengajuan Judul KKM/PKL/KKN (Kuliah Kerja Mahasiswa), Pengajuan PPL, Ujian Komprehensif, dan Pendaftaran Sidang: satu
 * halaman per jenis (rute dengan `jenis` bawaan) dengan form yang sama (judul/topik, keterangan, berkas syarat; KKM ditambah
 * pilihan KKM/PKL/KKN, kompre ditambah gelombang), diproses admin di menu Pengajuan & Pendaftaran. Kegiatannya berlangsung
 * di luar sistem; nilai KKM diisi admin di menu Nilai KKM, nilai PPL dan TA/Skripsi lewat Nilai Semester. Pendaftaran sidang
 * hanya ada saat fitur pendadaran mati dan butuh judul TA yang sudah disahkan. Setelah disetujui, jenis yang sama tidak bisa
 * diajukan lagi.
 */
class PengajuanKegiatanController extends Controller
{
    use KirimPengajuanAkademik;

    public const SELESAI = 'selesai';

    public const MENUNGGU = 'menunggu';

    public const PERBAIKAN = 'perbaikan';

    public const BARU = 'baru';

    /** Pendaftaran sidang sebelum judul TA disahkan. */
    public const BELUM_MEMENUHI = 'belum_memenuhi';

    private const EKSTENSI_DOKUMEN = 'pdf,jpg,jpeg,png';

    /** Rute halaman per jenis; alamat lama `pengajuan-kegiatan?jenis=…` dialihkan ke sini. */
    public const RUTE = [
        PengajuanAkademik::KKM => 'mahasiswa.pengajuan-kkm',
        PengajuanAkademik::PPL => 'mahasiswa.pengajuan-ppl',
        PengajuanAkademik::KOMPRE => 'mahasiswa.pengajuan-kompre',
        PengajuanAkademik::SIDANG => 'mahasiswa.pendaftaran-sidang',
    ];

    public const JUDUL = [
        PengajuanAkademik::KKM => 'Pengajuan Judul KKM/PKL/KKN',
        PengajuanAkademik::PPL => 'Pengajuan PPL',
        PengajuanAkademik::KOMPRE => 'Pengajuan Ujian Komprehensif',
        PengajuanAkademik::SIDANG => 'Pendaftaran Sidang',
    ];

    /** Label isian judul per jenis. */
    public const LABEL_JUDUL = [
        PengajuanAkademik::KKM => 'Judul KKM/PKL/KKN',
        PengajuanAkademik::PPL => 'Tempat / instansi PPL',
        PengajuanAkademik::KOMPRE => 'Bidang / topik ujian',
        PengajuanAkademik::SIDANG => 'Judul tugas akhir',
    ];

    public function index(Request $request): Response|RedirectResponse
    {
        $jenis = $request->route('jenis');
        if ($jenis === null) {
            return redirect()->route(self::RUTE[in_array($request->query('jenis'), PengajuanAkademik::JENIS_KEGIATAN, true) ? $request->query('jenis') : PengajuanAkademik::KKM]);
        }
        $mahasiswa = $this->mahasiswa($request);
        $pengajuan = PengajuanAkademik::terakhir($mahasiswa->id, $jenis);
        $keadaan = $this->keadaan($pengajuan, $jenis, $mahasiswa);

        return Inertia::render('Mahasiswa/PengajuanKegiatan', [
            'jenis' => $jenis,
            'judul' => self::JUDUL[$jenis],
            'rute' => self::RUTE[$jenis],
            'label' => PengajuanAkademik::LABEL_JENIS[$jenis],
            'labelJudul' => self::LABEL_JUDUL[$jenis],
            'jenisKkmOptions' => $jenis === PengajuanAkademik::KKM
                ? collect(PengajuanAkademik::JENIS_KKM)->map(fn (string $label, string $kunci): array => ['id' => $kunci, 'name' => $label])->values()
                : [],
            'keadaan' => $keadaan,
            // Kompre: gelombang yang menerima pendaftar (saat perbaikan, kuota tidak menghitung pengajuan sendiri).
            'gelombangOptions' => $jenis === PengajuanAkademik::KOMPRE
                ? GelombangKompre::terbuka($keadaan === self::PERBAIKAN ? $pengajuan?->id : null)->map(fn (GelombangKompre $g): array => [
                    'id' => $g->id,
                    'name' => $g->nama.' — ujian '.$g->tanggal_ujian->translatedFormat('j F Y'),
                    'sisa_kuota' => $g->sisaKuota($keadaan === self::PERBAIKAN ? $pengajuan?->id : null),
                ])
                : [],
            'gelombangDipilih' => $jenis === PengajuanAkademik::KOMPRE && $pengajuan !== null
                ? GelombangKompre::query()->find($pengajuan->isian['gelombang_kompre_id'] ?? 0)?->ringkas()
                : null,
            // Sidang: judul TA yang disahkan diisikan sebagai judul awal.
            'judulAwal' => $jenis === PengajuanAkademik::SIDANG ? TugasAkhir::milik($mahasiswa->id)?->judul : null,
            'pengajuan' => $pengajuan ? [
                'id' => $pengajuan->id,
                'status' => $pengajuan->status,
                'isian' => $pengajuan->isian,
                'lampiran' => array_keys($pengajuan->lampiran ?? []),
                'catatan' => $pengajuan->catatan,
                'diajukan_at' => $pengajuan->diajukan_at?->toIso8601String(),
                'diproses_at' => $pengajuan->diproses_at?->toIso8601String(),
            ] : null,
            'riwayat' => PengajuanAkademik::query()->where('mahasiswa_id', $mahasiswa->id)->where('jenis', $jenis)
                ->with('riwayat.pengguna:id,name')->latest('id')->get()
                ->map(fn (PengajuanAkademik $p): array => [
                    'id' => $p->id,
                    'jenis' => PengajuanAkademik::JENIS_KKM[$p->isian['jenis_kkm'] ?? ''] ?? PengajuanAkademik::LABEL_JENIS[$p->jenis],
                    'status' => $p->status,
                    'riwayat' => $p->riwayat->map(fn (RiwayatPengajuanAkademik $r): array => [
                        'status' => $r->status,
                        'catatan' => $r->catatan,
                        'oleh' => $r->pengguna?->name,
                        'waktu' => $r->created_at?->toIso8601String(),
                    ]),
                ]),
        ]);
    }

    public function ajukan(Request $request, string $jenis): RedirectResponse
    {
        abort_unless(in_array($jenis, PengajuanAkademik::JENIS_KEGIATAN, true), 404);
        abort_if($jenis === PengajuanAkademik::SIDANG && Feature::aktif('pendadaran'), 404);
        $mahasiswa = $this->mahasiswa($request);
        $label = PengajuanAkademik::LABEL_JENIS[$jenis];
        if ($request->filled('gelombang_kompre_id')) {
            // Disimpan sebagai angka di isian JSON agar cocok saat menghitung pendaftar per gelombang.
            $request->merge(['gelombang_kompre_id' => (int) $request->input('gelombang_kompre_id')]);
        }
        $pengajuanLama = PengajuanAkademik::terakhir($mahasiswa->id, $jenis);

        return $this->simpan($request, $mahasiswa, $jenis, fn (?PengajuanAkademik $pengajuan): string => $this->keadaan($pengajuan, $jenis, $mahasiswa), fn (bool $perbaikan): array => [
            ...($jenis === PengajuanAkademik::KKM ? ['jenis_kkm' => ['required', Rule::in(array_keys(PengajuanAkademik::JENIS_KKM))]] : []),
            ...($jenis === PengajuanAkademik::KOMPRE ? ['gelombang_kompre_id' => ['required', 'integer', function (string $atribut, mixed $nilai, \Closure $gagal) use ($perbaikan, $pengajuanLama): void {
                if (! GelombangKompre::query()->find($nilai)?->bisaDidaftar($perbaikan ? $pengajuanLama?->id : null)) {
                    $gagal('Gelombang ini sudah ditutup atau kuotanya penuh.');
                }
            }]] : []),
            'judul' => ['required', 'string', 'max:300'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'berkas_syarat' => [$perbaikan ? 'nullable' : 'required', 'file', 'max:5120', 'extensions:'.self::EKSTENSI_DOKUMEN, 'mimes:'.self::EKSTENSI_DOKUMEN],
            'berkas_tambahan' => ['nullable', 'file', 'max:5120', 'extensions:'.self::EKSTENSI_DOKUMEN, 'mimes:'.self::EKSTENSI_DOKUMEN],
        ], [
            '*.extensions' => ':attribute harus berupa PDF atau foto (JPG/PNG).',
            '*.max' => 'Ukuran :attribute maksimal 5 MB.',
        ], [
            'jenis_kkm' => 'Jenis kegiatan',
            'gelombang_kompre_id' => 'Gelombang ujian',
            'judul' => self::LABEL_JUDUL[$jenis],
            'keterangan' => 'Keterangan',
            'berkas_syarat' => 'Berkas syarat',
            'berkas_tambahan' => 'Berkas tambahan',
        ], [
            self::SELESAI => "Pengajuan {$label} Anda sudah disetujui.",
            self::MENUNGGU => 'Pengajuan Anda masih menunggu diproses admin.',
            self::BELUM_MEMENUHI => 'Judul tugas akhir Anda belum disahkan. Ajukan judul di menu Pengajuan Judul & Upload TA.',
        ], $label);
    }

    private function keadaan(?PengajuanAkademik $pengajuan, string $jenis, MahasiswaProfile $mahasiswa): string
    {
        return match (true) {
            $pengajuan?->status === PengajuanAkademik::DISETUJUI => self::SELESAI,
            $pengajuan?->sedangDiproses() === true => self::MENUNGGU,
            $pengajuan?->status === PengajuanAkademik::PERLU_PERBAIKAN => self::PERBAIKAN,
            $jenis === PengajuanAkademik::SIDANG && TugasAkhir::milik($mahasiswa->id) === null => self::BELUM_MEMENUHI,
            default => self::BARU,
        };
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }
}
