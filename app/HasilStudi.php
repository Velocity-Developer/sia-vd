<?php

namespace App;

use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanInstitusi;
use App\Models\TahunAkademik;
use App\Models\TugasAkhir;
use App\Models\Wisuda;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Data dan PDF Kartu Hasil Studi (per tahun akademik) serta Transkrip Nilai seorang mahasiswa. Dipakai halaman
 * mahasiswa sendiri dan menu admin Akademik → Hasil Studi. Hanya nilai yang sudah divalidasi (Penilaian → Validasi
 * Nilai) yang dihitung; nilai yang belum divalidasi tampil "menunggu validasi" di KHS dan belum masuk transkrip.
 */
class HasilStudi
{
    /**
     * @return array{transkrip: Collection<int, array<string, mixed>>, ringkasan: array{totalMatkul: int, totalSks: int, totalSksLulus: int, totalMutu: float, ipk: float|null}, kelulusan: array{judul_ta: ?string, tanggal_lulus: ?string}}
     */
    public static function transkrip(MahasiswaProfile $mahasiswa): array
    {
        $krs = Transkrip::krs($mahasiswa->id)->whereNotNull('nilai')->filter(fn (Krs $item): bool => $item->nilaiTervalidasi());
        $jumlahPengambilan = $krs->countBy(fn (Krs $item): ?int => $item->kelasKuliah?->matkul_id);
        // Mata kuliah yang diulang hanya dihitung sekali, memakai nilai terbaiknya.
        $transkrip = Transkrip::terbaik($krs)
            ->map(fn (Krs $item): array => [
                'id' => $item->id,
                'kode' => $item->kelasKuliah->mataKuliah->kode_matkul,
                'nama' => $item->kelasKuliah->mataKuliah->nama_matkul,
                'jenis' => $item->kelasKuliah->mataKuliah->jenis,
                'sks' => $item->kelasKuliah->mataKuliah->sks,
                'nilai' => strtoupper($item->nilai),
                'bobot' => $item->bobotNilai(),
                'mutu' => $item->kelasKuliah->mataKuliah->sks * $item->bobotNilai(),
                'lulus' => $item->nilaiLulus(),
                'diambil' => $jumlahPengambilan[$item->kelasKuliah->matkul_id] ?? 1,
            ])
            ->sortBy('kode')
            ->values();
        $totalSks = $transkrip->sum('sks');
        $totalMutu = $transkrip->sum('mutu');
        $totalSksLulus = $transkrip->filter(fn (array $item): bool => $item['lulus'])->sum('sks');

        return [
            'transkrip' => $transkrip,
            'ringkasan' => ['totalMatkul' => $transkrip->count(), 'totalSks' => $totalSks, 'totalSksLulus' => $totalSksLulus, 'totalMutu' => $totalMutu, 'ipk' => $totalSks > 0 ? round($totalMutu / $totalSks, 2) : null],
            // Judul TA yang disahkan dan tanggal lulus (yudisium) dari pendaftaran wisuda yang disetujui.
            'kelulusan' => [
                'judul_ta' => TugasAkhir::milik($mahasiswa->id)?->judul,
                'tanggal_lulus' => Wisuda::query()->where('mahasiswa_id', $mahasiswa->id)->first(['tanggal_lulus'])?->tanggal_lulus?->toDateString(),
            ],
        ];
    }

    public static function unduhTranskrip(MahasiswaProfile $mahasiswa): Response
    {
        $mahasiswa->muatPengesahan();
        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView('pdf.transkrip', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'mahasiswa' => $mahasiswa,
            ...self::transkrip($mahasiswa),
        ])->download("transkrip-{$mahasiswa->nim}.pdf");
    }

    /**
     * Tanpa tahun akademik (atau id tidak dikenal) memakai tahun akademik aktif, lalu yang terbaru.
     *
     * @return array{krs: Collection<int, array<string, mixed>>, tahunAkademiks: Collection<int, TahunAkademik>, tahunAkademik: TahunAkademik|null, ringkasan: array{totalSks: int, totalSksDinilai: int, totalMutu: float, ip: float|null}}
     */
    public static function khs(MahasiswaProfile $mahasiswa, ?int $tahunAkademikId): array
    {
        $tahunAkademik = TahunAkademik::query()
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'tahun', 'semester', 'status']);
        $tahunAkademikAktif = $tahunAkademik->firstWhere('status', true) ?? $tahunAkademik->first();
        $tahunAkademikTerpilih = $tahunAkademikId ? $tahunAkademik->firstWhere('id', $tahunAkademikId) : $tahunAkademikAktif;
        $tahunAkademikTerpilih ??= $tahunAkademikAktif;

        $krs = Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->when($tahunAkademikTerpilih, fn ($query) => $query->whereHas('kelasKuliah', fn ($kelas) => $kelas->where('tahun_akademik_id', $tahunAkademikTerpilih->id)))
            ->with(['kelasKuliah:id,matkul_id,tahun_akademik_id', 'kelasKuliah.mataKuliah:id,prodi_id,kode_matkul,nama_matkul,sks,tugas_akhir', 'kelasKuliah.tahunAkademik:id,status'])
            ->get();
        $krsDinilai = $krs->filter(fn (Krs $item): bool => $item->nilaiTervalidasi() && $item->bobotNilai() !== null && ($item->kelasKuliah?->mataKuliah?->sks ?? 0) > 0);
        $totalSksDinilai = $krsDinilai->sum(fn (Krs $item): int => $item->kelasKuliah->mataKuliah->sks);
        $totalMutu = $krsDinilai->sum(fn (Krs $item): float => $item->kelasKuliah->mataKuliah->sks * $item->bobotNilai());

        return [
            // Halaman KHS dan PDF hanya menampilkan kode, nama, SKS, dan nilai.
            'krs' => $krs->map(fn (Krs $item): array => [
                'id' => $item->id,
                'kode' => $item->kelasKuliah?->mataKuliah?->kode_matkul,
                'nama' => $item->kelasKuliah?->mataKuliah?->nama_matkul,
                'sks' => $item->kelasKuliah?->mataKuliah?->sks,
                'nilai' => $item->nilaiTervalidasi() ? $item->nilai : null,
                'menunggu_validasi' => filled($item->nilai) && ! $item->nilaiTervalidasi(),
                // TA/Skripsi yang belum selesai di semester itu dan diambil lagi semester berikutnya.
                'berlanjut' => $item->taBerlanjut(),
            ])->values(),
            'tahunAkademiks' => $tahunAkademik,
            'tahunAkademik' => $tahunAkademikTerpilih,
            'ringkasan' => [
                'totalSks' => $krs->sum(fn (Krs $item): int => $item->kelasKuliah?->mataKuliah?->sks ?? 0),
                'totalSksDinilai' => $totalSksDinilai,
                'totalMutu' => $totalMutu,
                'ip' => $totalSksDinilai > 0 ? round($totalMutu / $totalSksDinilai, 2) : null,
            ],
        ];
    }

    /**
     * Props halaman KHS (komponen Vue KartuHasilStudi).
     *
     * @return array<string, mixed>
     */
    public static function propsKhs(MahasiswaProfile $mahasiswa, ?int $tahunAkademikId): array
    {
        $data = self::khs($mahasiswa, $tahunAkademikId);

        return [
            'krs' => $data['krs'],
            'tahunAkademiks' => $data['tahunAkademiks'],
            'tahunAkademikTerpilih' => $data['tahunAkademik']?->id,
            'ringkasan' => $data['ringkasan'],
        ];
    }

    public static function unduhKhs(MahasiswaProfile $mahasiswa, ?int $tahunAkademikId): Response
    {
        $data = self::khs($mahasiswa, $tahunAkademikId);
        $institusi = PengaturanInstitusi::current();

        $pdf = Pdf::loadView('pdf.khs', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'mahasiswa' => $mahasiswa->muatPengesahan(),
            'tahunAkademik' => $data['tahunAkademik'],
            'krs' => $data['krs'],
            'ringkasan' => $data['ringkasan'],
        ]);

        $tahun = str_replace('/', '-', (string) $data['tahunAkademik']?->tahun);

        return $pdf->download("khs-{$mahasiswa->nim}-{$tahun}-{$data['tahunAkademik']?->semester}.pdf");
    }
}
