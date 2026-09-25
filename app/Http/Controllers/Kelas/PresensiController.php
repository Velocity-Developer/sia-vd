<?php

namespace App\Http\Controllers\Kelas;

use App\Http\Controllers\Concerns\AksesPresensi;
use App\Http\Controllers\Concerns\FilterKelasKuliah;
use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\DispensasiUjian;
use App\Models\KelasKuliah;
use App\Models\PengajuanIzin;
use App\Models\PengaturanAkademik;
use App\Models\PengaturanInstitusi;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\ProgramStudi;
use App\Models\Ruang;
use App\Models\TahunAkademik;
use App\SyaratUjian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PresensiController extends Controller
{
    use AksesPresensi, FilterKelasKuliah, KontenKelas;

    /**
     * Menu Presensi: pertemuan hari ini dan daftar kelas beserta kemajuan pertemuannya.
     */
    public function index(Request $request): Response
    {
        Pertemuan::tutupYangLewat();
        $filter = $this->filterKelas($request);
        // Kaprodi bisa beralih dari "kelas saya" ke seluruh kelas di prodi yang dipimpinnya (hanya melihat).
        $lingkupProdi = $this->peran() === 'dosen' && $request->query('lingkup') === 'prodi' && $this->prodiKaprodi() !== [];

        if ($lingkupProdi) {
            $filter['dosen_id'] = null;
            $filter['prodi_ids'] = $this->prodiKaprodi();
        }

        $saringKelas = fn (Builder $kelas) => $kelas
            ->when($lingkupProdi, fn (Builder $q) => $q->whereHas('mataKuliah', fn (Builder $matkul) => $matkul->whereIn('prodi_id', $this->prodiKaprodi())))
            ->when($filter['tahun_akademik_id'] !== null, fn (Builder $q) => $q->where('tahun_akademik_id', $filter['tahun_akademik_id']))
            ->when($filter['kelas_id'] !== null, fn (Builder $q) => $q->whereKey($filter['kelas_id']))
            ->when($filter['dosen_id'] !== null, fn (Builder $q) => $q->where('dosen_id', $filter['dosen_id']))
            ->when($filter['mata_kuliah_id'] !== null, fn (Builder $q) => $q->where('matkul_id', $filter['mata_kuliah_id']))
            ->when($filter['prodi_id'] !== null, fn (Builder $q) => $q->whereHas('mataKuliah', fn (Builder $matkul) => $matkul->where('prodi_id', $filter['prodi_id'])))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $cari) => $cari
                ->where('kode_kelas', 'like', "%{$filter['search']}%")
                ->orWhereHas('mataKuliah', fn (Builder $matkul) => $matkul->where('nama_matkul', 'like', "%{$filter['search']}%")->orWhere('kode_matkul', 'like', "%{$filter['search']}%"))));

        $kelas = KelasKuliah::query()
            ->tap($saringKelas)
            ->with(['mataKuliah:id,kode_matkul,nama_matkul', 'dosen:id,user_id', 'dosen.user:id,name', 'tahunAkademik:id,tahun,semester'])
            ->withCount([
                'pertemuans as pertemuan_dibuat',
                'pertemuans as pertemuan_selesai' => fn ($query) => $query->where('status', Pertemuan::SELESAI),
                'krs as jumlah_peserta',
            ])
            ->orderBy('kode_kelas')
            ->paginate(15)
            ->withQueryString();

        $rataRata = $this->rataRataKehadiran($kelas->getCollection()->pluck('id')->all());
        $kelas->getCollection()->each(fn (KelasKuliah $item) => $item->setAttribute('rata_kehadiran', $rataRata[$item->id] ?? null));

        $hariIni = Pertemuan::query()
            ->whereDate('tanggal', today())
            ->where('status', '!=', Pertemuan::DIBATALKAN)
            // Dosen juga melihat pertemuan kelas lain yang ditunjukkan kepadanya sebagai pengganti.
            ->where(fn ($query) => $query
                ->whereHas('kelasKuliah', $saringKelas)
                ->when($this->peran() === 'dosen' && ! $lingkupProdi, fn ($q) => $q->orWhere('dosen_id', $this->dosenId())))
            ->with(['kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul', 'ruang:id,kode_ruang'])
            ->orderBy('jam_mulai')
            ->limit(50)
            ->get(['id', 'kelas_id', 'pertemuan_ke', 'tanggal', 'jam_mulai', 'jam_akhir', 'ruang_id', 'jenis', 'status']);

        return Inertia::render('Kelas/PresensiIndex', [
            'kelas' => $kelas,
            'hariIni' => $hariIni,
            'lingkup' => $lingkupProdi ? 'prodi' : 'saya',
            'bisaLingkupProdi' => $this->peran() === 'dosen' && $this->prodiKaprodi() !== [],
            'izinMenunggu' => PengajuanIzin::query()
                ->where('status', PengajuanIzin::MENUNGGU)
                ->when($this->peran() === 'dosen', fn ($q) => $q->whereHas('pertemuan.kelasKuliah', fn ($kelas) => $kelas->where('dosen_id', $this->dosenId())))
                ->count(),
            ...$this->propsFilterKelas($filter),
        ]);
    }

    /**
     * Daftar pertemuan satu kelas dan rekap kehadiran mahasiswa.
     */
    public function kelas(KelasKuliah $kelasKuliah): Response
    {
        $this->pastikanLihatKelas($kelasKuliah);
        Pertemuan::tutupYangLewat();

        $kelasKuliah->load(['mataKuliah:id,kode_matkul,nama_matkul,sks,prodi_id', 'dosen:id,user_id,nidn', 'dosen.user:id,name', 'tahunAkademik:id,tahun,semester,tanggal_mulai,tanggal_akhir,status', 'jadwals:id,kelas_id,hari,jam_mulai,jam_akhir']);

        ['pertemuan' => $pertemuan, 'peserta' => $peserta] = $this->dataRekap($kelasKuliah);
        $ujian = SyaratUjian::untukKelas($kelasKuliah);

        return Inertia::render('Kelas/PresensiKelas', [
            'peran' => $this->peran(),
            'bisaKelola' => $this->pengampu($kelasKuliah),
            'bisaDispensasi' => $this->bolehDispensasi($kelasKuliah),
            'kelasKuliah' => $kelasKuliah,
            'pertemuan' => $pertemuan,
            'peserta' => $peserta->map(fn (array $baris): array => [
                ...$baris,
                'presensi' => $baris['presensi'] ?: (object) [],
                'ujian' => $ujian['peserta'][$baris['mahasiswa_id']] ?? null,
            ]),
            'ujian' => [
                'aktif' => $ujian['aktif'],
                'uts' => self::jadwalUjian($ujian['jadwal']['uts']),
                'uas' => self::jadwalUjian($ujian['jadwal']['uas']),
            ],
            'minKehadiran' => PengaturanAkademik::current()->min_kehadiran_ujian,
            'terkunci' => $this->tahunAkademikTerkunci($kelasKuliah),
            'ruangs' => Ruang::orderBy('kode_ruang')->get(['id', 'kode_ruang', 'nama_ruang'])
                ->map(fn (Ruang $ruang): array => ['id' => $ruang->id, 'name' => $ruang->kode_ruang.' — '.$ruang->nama_ruang]),
        ]);
    }

    /**
     * Unduh rekap presensi kelas: PDF (daftar hadir + jurnal perkuliahan, siap cetak) atau CSV untuk spreadsheet.
     */
    public function ekspor(Request $request, KelasKuliah $kelasKuliah): HttpResponse|StreamedResponse
    {
        $this->pastikanLihatKelas($kelasKuliah);
        $kelasKuliah->load(['mataKuliah:id,kode_matkul,nama_matkul,sks,prodi_id', 'mataKuliah.prodi:id,nama_prodi,jenjang', 'dosen:id,user_id,nidn', 'dosen.user:id,name', 'tahunAkademik:id,tahun,semester']);
        ['pertemuan' => $pertemuan, 'peserta' => $peserta] = $this->dataRekap($kelasKuliah);
        $nama = 'presensi-'.Str::slug($kelasKuliah->kode_kelas.'-'.$kelasKuliah->tahunAkademik?->tahun.'-'.$kelasKuliah->tahunAkademik?->semester);
        $singkat = fn (?string $status): string => $status ? strtoupper($status[0]) : '';

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($pertemuan, $peserta, $singkat): void {
                $keluar = fopen('php://output', 'w');
                fwrite($keluar, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
                fputcsv($keluar, ['NIM', 'Nama', ...$pertemuan->map(fn (Pertemuan $p): string => $p->jenis === Pertemuan::KULIAH ? 'P'.$p->pertemuan_ke : strtoupper($p->jenis)), 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', 'Kehadiran (%)']);

                foreach ($peserta as $baris) {
                    $rekap = $baris['rekap'];
                    fputcsv($keluar, [
                        $baris['nim'], $baris['nama'],
                        ...$pertemuan->map(fn (Pertemuan $p): string => $singkat($baris['presensi'][$p->id] ?? null)),
                        $rekap['hadir'] ?? 0, $rekap['terlambat'] ?? 0, $rekap['izin'] ?? 0, $rekap['sakit'] ?? 0, $rekap['alpa'] ?? 0,
                        $rekap['persen'] ?? '',
                    ]);
                }

                fclose($keluar);
            }, $nama.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView('pdf.presensi', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'kelas' => $kelasKuliah,
            'pertemuan' => $pertemuan,
            'peserta' => $peserta,
            'singkat' => $singkat,
            'minKehadiran' => PengaturanAkademik::current()->min_kehadiran_ujian,
        ])->setPaper('a4', 'landscape')->download($nama.'.pdf');
    }

    /**
     * Beri dispensasi UTS dan/atau UAS kepada satu mahasiswa (admin atau kaprodi).
     */
    public function dispensasiSimpan(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        abort_unless($this->bolehDispensasi($kelasKuliah), 403);

        $data = $request->validate([
            'mahasiswa_id' => ['required', 'integer', Rule::exists('krs', 'mahasiswa_id')->where('kelas_id', $kelasKuliah->id)],
            'jenis' => ['required', 'array', 'min:1'],
            'jenis.*' => [Rule::in(DispensasiUjian::JENIS)],
            'alasan' => ['required', 'string', 'max:255'],
        ], ['mahasiswa_id.exists' => 'Mahasiswa tidak terdaftar di kelas ini.'], ['jenis' => 'Ujian', 'alasan' => 'Alasan']);

        foreach (array_unique($data['jenis']) as $jenis) {
            DispensasiUjian::updateOrCreate(
                ['kelas_id' => $kelasKuliah->id, 'mahasiswa_id' => $data['mahasiswa_id'], 'jenis' => $jenis],
                ['alasan' => $data['alasan'], 'diberikan_oleh' => $request->user()->id],
            );
        }

        return back()->with('success', 'Dispensasi '.strtoupper(implode(' & ', array_unique($data['jenis']))).' diberikan.');
    }

    public function dispensasiHapus(DispensasiUjian $dispensasi): RedirectResponse
    {
        abort_unless($this->bolehDispensasi($dispensasi->kelasKuliah), 403);
        $dispensasi->delete();

        return back()->with('success', 'Dispensasi '.strtoupper($dispensasi->jenis).' dicabut.');
    }

    /**
     * Daftar hadir ujian (tatap muka) siap cetak: seluruh peserta dengan status syarat kehadirannya
     * dan kolom tanda tangan.
     */
    public function pesertaUjian(Request $request, KelasKuliah $kelasKuliah): HttpResponse
    {
        $this->pastikanLihatKelas($kelasKuliah);
        $jenis = $request->query('jenis') === Pertemuan::UAS ? Pertemuan::UAS : Pertemuan::UTS;
        $kelasKuliah->load(['mataKuliah:id,kode_matkul,nama_matkul,sks,prodi_id', 'mataKuliah.prodi:id,nama_prodi', 'dosen:id,user_id,nidn', 'dosen.user:id,name', 'tahunAkademik:id,tahun,semester']);
        $ujian = SyaratUjian::untukKelas($kelasKuliah);
        abort_if($ujian['jadwal'][$jenis] === null, 404, 'Kelas ini belum punya pertemuan '.strtoupper($jenis).'.');

        $peserta = $kelasKuliah->krs()
            ->with(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name'])
            ->get(['id', 'kelas_id', 'mahasiswa_id'])
            ->sortBy(fn ($krs) => $krs->mahasiswa?->nim)
            ->values()
            ->map(fn ($krs): array => [
                'nim' => $krs->mahasiswa?->nim,
                'nama' => $krs->mahasiswa?->user?->name,
                'syarat' => $ujian['peserta'][$krs->mahasiswa_id][$jenis] ?? null,
            ]);
        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView('pdf.peserta-ujian', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'kelas' => $kelasKuliah,
            'jenis' => $jenis,
            'jadwal' => $ujian['jadwal'][$jenis],
            'aktif' => $ujian['aktif'],
            'min' => $ujian['min'],
            'peserta' => $peserta,
        ])->download('peserta-'.$jenis.'-'.Str::slug($kelasKuliah->kode_kelas.'-'.$kelasKuliah->tahunAkademik?->tahun.'-'.$kelasKuliah->tahunAkademik?->semester).'.pdf');
    }

    /**
     * Laporan kehadiran dosen per kelas pada satu tahun akademik (hanya admin).
     */
    public function laporanDosen(Request $request): Response|StreamedResponse
    {
        Pertemuan::tutupYangLewat();
        $tahunAkademiks = TahunAkademik::orderByDesc('tanggal_mulai')->get(['id', 'tahun', 'semester', 'status']);
        $tahunId = $request->integer('tahun_akademik_id') ?: $tahunAkademiks->firstWhere('status', true)?->id;
        $prodiId = $request->integer('prodi_id') ?: null;
        $toleransi = PengaturanAkademik::current()->toleransi_terlambat_menit;

        $kelas = KelasKuliah::query()
            ->where('tahun_akademik_id', $tahunId)
            ->when($prodiId, fn ($q) => $q->whereHas('mataKuliah', fn ($m) => $m->where('prodi_id', $prodiId)))
            ->with(['mataKuliah:id,kode_matkul,nama_matkul,prodi_id', 'dosen:id,user_id,nidn', 'dosen.user:id,name', 'pertemuans'])
            ->get();
        $rataRata = $this->rataRataKehadiran($kelas->pluck('id')->all());

        $baris = $kelas->map(function (KelasKuliah $item) use ($toleransi, $rataRata): array {
            $pertemuan = $item->pertemuans;
            $selesai = $pertemuan->where('status', Pertemuan::SELESAI);

            return [
                'dosen' => $item->dosen?->user?->name ?? '-',
                'nidn' => $item->dosen?->nidn,
                'kelas_id' => $item->id,
                'kode_kelas' => $item->kode_kelas,
                'mata_kuliah' => $item->mataKuliah?->nama_matkul,
                'rencana' => $item->jumlah_pertemuan,
                'terlaksana' => $selesai->count(),
                'dibatalkan' => $pertemuan->where('status', Pertemuan::DIBATALKAN)->count(),
                'terlewat' => $pertemuan->filter(fn (Pertemuan $p): bool => $p->terlewat())->count(),
                'oleh_pengganti' => $selesai->filter(fn (Pertemuan $p): bool => $p->dosen_id !== null && $p->dosen_id !== $item->dosen_id)->count(),
                'terlambat' => $selesai->filter(fn (Pertemuan $p): bool => $p->dosen_masuk_at !== null && $p->dosen_masuk_at->gt($p->mulaiAt()->addMinutes($toleransi)))->count(),
                'tanpa_jurnal' => $selesai->filter(fn (Pertemuan $p): bool => blank($p->topik))->count(),
                'rata_kehadiran' => $rataRata[$item->id] ?? null,
            ];
        })->sortBy([['dosen', 'asc'], ['kode_kelas', 'asc']])->values();

        if ($request->query('format') === 'csv') {
            $tahun = $tahunAkademiks->firstWhere('id', $tahunId);

            return response()->streamDownload(function () use ($baris): void {
                $keluar = fopen('php://output', 'w');
                fwrite($keluar, "\xEF\xBB\xBF");
                fputcsv($keluar, ['Dosen', 'NIDN', 'Kelas', 'Mata Kuliah', 'Rencana', 'Terlaksana', 'Dibatalkan', 'Terlewat', 'Oleh Pengganti', 'Masuk Terlambat', 'Tanpa Jurnal', 'Rata-rata Hadir Mahasiswa (%)']);
                foreach ($baris as $b) {
                    fputcsv($keluar, [$b['dosen'], $b['nidn'], $b['kode_kelas'], $b['mata_kuliah'], $b['rencana'], $b['terlaksana'], $b['dibatalkan'], $b['terlewat'], $b['oleh_pengganti'], $b['terlambat'], $b['tanpa_jurnal'], $b['rata_kehadiran'] ?? '']);
                }
                fclose($keluar);
            }, 'laporan-kehadiran-dosen-'.Str::slug(($tahun?->tahun ?? '').'-'.($tahun?->semester ?? '')).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return Inertia::render('Kelas/LaporanKehadiranDosen', [
            'baris' => $baris,
            'toleransi' => $toleransi,
            'tahunAkademikId' => $tahunId,
            'prodiId' => $prodiId,
            'tahunAkademikOptions' => $tahunAkademiks->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->tahun.' '.$t->semester]),
            'prodiOptions' => ProgramStudi::orderBy('nama_prodi')->get(['id', 'nama_prodi'])->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => $p->nama_prodi]),
        ]);
    }

    /**
     * Susun ulang pertemuan yang belum berjalan mengikuti jadwal mingguan dan tanggal tahun akademik terbaru.
     */
    public function susunUlang(KelasKuliah $kelasKuliah): RedirectResponse
    {
        $this->pastikanPengampu($kelasKuliah);
        abort_if($this->tahunAkademikTerkunci($kelasKuliah), 403);
        $hasil = Pertemuan::susunUlang($kelasKuliah);

        return back()->with('success', $hasil['diubah'] > 0
            ? "{$hasil['diubah']} pertemuan disesuaikan dengan jadwal terbaru.".($hasil['dilewati'] > 0 ? " {$hasil['dilewati']} pertemuan yang tanggalnya sudah lewat tidak diubah." : '')
            : 'Semua pertemuan yang belum berjalan sudah sesuai jadwal.');
    }

    public function generate(KelasKuliah $kelasKuliah): RedirectResponse
    {
        $this->pastikanPengampu($kelasKuliah);
        $hasil = Pertemuan::generateUntuk($kelasKuliah);

        if ($hasil['dibuat'] === 0) {
            return back()->with('success', 'Semua pertemuan sudah dibuat; tidak ada yang ditambahkan.');
        }

        $pesan = "{$hasil['dibuat']} pertemuan dibuat dari jadwal mingguan.";

        if ($hasil['lewat_akhir'] > 0) {
            $pesan .= " Perhatian: {$hasil['lewat_akhir']} pertemuan jatuh sesudah tanggal akhir tahun akademik, periksa tanggalnya.";
        }

        return back()->with('success', $pesan);
    }

    /**
     * Ubah jumlah pertemuan satu kelas (hanya admin).
     */
    public function ubahJumlah(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $data = $request->validate(
            ['jumlah_pertemuan' => ['required', 'integer', 'min:1', 'max:32']],
            attributes: ['jumlah_pertemuan' => 'Jumlah pertemuan'],
        );

        DB::transaction(fn () => $kelasKuliah->ubahJumlahPertemuan((int) $data['jumlah_pertemuan']));

        return back()->with('success', 'Jumlah pertemuan kelas diubah menjadi '.$data['jumlah_pertemuan'].'.');
    }

    /**
     * Pertemuan kelas dan peserta beserta status tiap pertemuan & rekapnya, untuk halaman dan ekspor.
     *
     * @return array{pertemuan: Collection<int, Pertemuan>, peserta: SupportCollection<int, array{mahasiswa_id: int, nim: ?string, nama: ?string, presensi: array<int, string>, rekap: ?array<string, mixed>}>}
     */
    private function dataRekap(KelasKuliah $kelasKuliah): array
    {
        $pertemuan = $kelasKuliah->pertemuans()
            ->with(['ruang:id,kode_ruang,nama_ruang', 'dosen:id,user_id', 'dosen.user:id,name'])
            ->withCount([
                'presensiMahasiswas as jumlah_tercatat',
                'presensiMahasiswas as jumlah_hadir' => fn ($query) => $query->whereIn('status', PresensiMahasiswa::DIHITUNG_HADIR),
            ])
            ->orderBy('pertemuan_ke')
            ->get();

        // Matriks mahasiswa × pertemuan: [mahasiswa_id][pertemuan_id] = status.
        $matriks = PresensiMahasiswa::query()
            ->whereIn('pertemuan_id', $pertemuan->pluck('id'))
            ->get(['pertemuan_id', 'mahasiswa_id', 'status'])
            ->groupBy('mahasiswa_id')
            ->map(fn ($baris) => $baris->pluck('status', 'pertemuan_id')->all());

        $rekap = PresensiMahasiswa::rekapKelas($kelasKuliah->id);

        $peserta = $kelasKuliah->krs()
            ->with(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name'])
            ->get(['id', 'kelas_id', 'mahasiswa_id'])
            ->sortBy(fn ($krs) => $krs->mahasiswa?->nim)
            ->values()
            ->map(fn ($krs): array => [
                'mahasiswa_id' => $krs->mahasiswa_id,
                'nim' => $krs->mahasiswa?->nim,
                'nama' => $krs->mahasiswa?->user?->name,
                'presensi' => $matriks[$krs->mahasiswa_id] ?? [],
                'rekap' => $rekap[$krs->mahasiswa_id] ?? null,
            ]);

        return ['pertemuan' => $pertemuan, 'peserta' => $peserta];
    }

    /**
     * Ringkasan jadwal UTS/UAS (pertemuan berjenis ujian) untuk ditampilkan.
     *
     * @return array<string, mixed>|null
     */
    public static function jadwalUjian(?Pertemuan $ujian): ?array
    {
        return $ujian === null ? null : [
            ...$ujian->only(['id', 'pertemuan_ke', 'jam_mulai', 'jam_akhir', 'status']),
            'tanggal' => $ujian->tanggal->toDateString(),
            'ruang' => $ujian->ruang?->kode_ruang,
        ];
    }

    /**
     * Persentase hadir rata-rata per kelas dari pertemuan yang dihitung.
     *
     * @param  list<int>  $kelasIds
     * @return array<int, float>
     */
    private function rataRataKehadiran(array $kelasIds): array
    {
        if ($kelasIds === []) {
            return [];
        }

        $hadir = "'".implode("','", PresensiMahasiswa::DIHITUNG_HADIR)."'";

        return DB::table('presensi_mahasiswas')
            ->join('pertemuans', 'pertemuans.id', '=', 'presensi_mahasiswas.pertemuan_id')
            ->whereIn('pertemuans.kelas_id', $kelasIds)
            ->where('pertemuans.jenis', Pertemuan::KULIAH)
            ->where('pertemuans.status', Pertemuan::SELESAI)
            ->whereExists(PresensiMahasiswa::syaratPesertaAktif())
            ->groupBy('pertemuans.kelas_id')
            ->selectRaw("pertemuans.kelas_id, SUM(CASE WHEN presensi_mahasiswas.status IN ({$hadir}) THEN 1 ELSE 0 END) as hadir, COUNT(*) as total")
            ->get()
            ->mapWithKeys(fn ($baris): array => [(int) $baris->kelas_id => round($baris->hadir / max($baris->total, 1) * 100, 1)])
            ->all();
    }
}
