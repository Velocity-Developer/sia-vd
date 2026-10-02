<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Feature;
use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\PengaturanAkademik;
use App\Models\PengaturanInstitusi;
use App\Models\TahunAkademik;
use App\TawaranKrs;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KrsController extends Controller
{
    public function index(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $tahunAkademik = TahunAkademik::aktif();
        $kunci = $tahunAkademik === null ? null : KrsSemester::untuk($mahasiswa->id, $tahunAkademik->id);
        // Kelas ditawarkan selama periode KRS, atau selama KRS dikembalikan untuk revisi / dibuka admin.
        $periodeKrsAktif = $this->periodeKrsAktif($tahunAkademik) || ($kunci?->setRelation('tahunAkademik', $tahunAkademik)->bisaDirevisi() ?? false);
        // Seluruh KRS mahasiswa dimuat sekali, lalu dipakai untuk tawaran, IPS, dan ringkasan SKS.
        $bolehKrs = in_array($mahasiswa->status, Krs::STATUS_MAHASISWA_BOLEH_KRS, true);
        $semuaKrs = $this->semuaKrs($mahasiswa);
        $tawaran = new TawaranKrs($mahasiswa, $tahunAkademik, $semuaKrs);
        $kelasDiambil = $semuaKrs->pluck('kelas_id');

        $kelasKuliahs = KelasKuliah::query()
            // Di luar periode atau status tidak boleh KRS (Cuti, Lulus): tidak ada kelas yang ditawarkan.
            ->when(! $periodeKrsAktif || ! $bolehKrs, fn ($query) => $query->whereKey(0))
            ->with([
                'mataKuliah.prodi',
                'mataKuliah.prasyarat:mata_kuliahs.id,nama_matkul',
                'tahunAkademik',
                'dosen:id,user_id',
                'dosen.user:id,name',
                'jadwals' => fn ($query) => $query->with('ruang')->orderBy('jam_mulai'),
            ])
            ->withCount('krs')
            ->whereHas('mataKuliah', fn ($query) => $query->where('prodi_id', $mahasiswa->prodi_id))
            ->whereHas('tahunAkademik', fn ($query) => $query->where('status', true))
            ->orderBy('kode_kelas')
            ->get()
            // Kelas yang sudah diambil tetap tampil agar bisa dibatalkan.
            ->filter(fn (KelasKuliah $kelas): bool => $kelasDiambil->contains($kelas->id) || $tawaran->jenis($kelas->mataKuliah) !== null)
            ->values();

        $ipsSebelumnya = $mahasiswa->ipsSemesterSebelum($tahunAkademik, $semuaKrs);
        $krsTahunIni = $tahunAkademik === null
            ? collect()
            : $semuaKrs->filter(fn (Krs $krs): bool => $krs->kelasKuliah?->tahun_akademik_id === $tahunAkademik->id);

        return Inertia::render('Mahasiswa/Krs', [
            'kelasKuliahs' => $kelasKuliahs,
            'mahasiswa' => $mahasiswa->only(['angkatan', 'prodi_id', 'status']) + ['semester' => $tawaran->semester()],
            'kelasDiambil' => $kelasDiambil->values(),
            'krsTahunIni' => $krsTahunIni->map(fn (Krs $krs): array => ['id' => $krs->id, 'kelas_id' => $krs->kelas_id, 'nilai' => $krs->nilai])->values(),
            'labelMatkul' => $kelasKuliahs->pluck('mataKuliah')->unique('id')
                ->mapWithKeys(fn (MataKuliah $mataKuliah): array => [$mataKuliah->id => $tawaran->label($mataKuliah)])
                ->filter(),
            'terkunciMatkul' => $kelasKuliahs->pluck('mataKuliah')->unique('id')
                ->mapWithKeys(fn (MataKuliah $mataKuliah): array => [$mataKuliah->id => $tawaran->alasanPrasyarat($mataKuliah)])
                ->filter(),
            'sksDiambil' => $krsTahunIni->sum(fn (Krs $krs): int => $krs->kelasKuliah?->mataKuliah?->sks ?? 0),
            'maksSks' => PengaturanAkademik::maksSksUntuk($ipsSebelumnya['ips'] ?? null, $mahasiswa->prodi_id),
            'ipsSebelumnya' => $ipsSebelumnya === null ? null : [
                'ips' => $ipsSebelumnya['ips'],
                'tahun_akademik' => $ipsSebelumnya['tahun_akademik']->tahun.' '.$ipsSebelumnya['tahun_akademik']->semester,
            ],
            'bolehKrs' => $bolehKrs,
            'tahunAkademik' => $tahunAkademik,
            'periodeKrsAktif' => $periodeKrsAktif,
            'krsTersimpan' => in_array($kunci?->status, KrsSemester::STATUS_TERKUNCI, true),
            'statusKrs' => $kunci?->ringkasan(),
            'verifikasiKrs' => KrsSemester::verifikasiAktif(),
            'batasRevisi' => $tahunAkademik === null ? null : KrsSemester::batasRevisi($tahunAkademik)?->toDateString(),
        ]);
    }

    public function store(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        $kelasKuliah->loadMissing('mataKuliah.prasyarat:mata_kuliahs.id,nama_matkul', 'tahunAkademik');

        abort_unless($kelasKuliah->mataKuliah?->prodi_id === $mahasiswa->prodi_id && $kelasKuliah->tahunAkademik?->status === true, 404);

        if (($alasan = $this->alasanTidakBolehUbah($mahasiswa, $kelasKuliah->tahunAkademik)) !== null) {
            return back()->with('krs_error', $alasan);
        }

        if (! in_array($mahasiswa->status, Krs::STATUS_MAHASISWA_BOLEH_KRS, true)) {
            return back()->with('krs_error', 'Status akademik Anda ('.($mahasiswa->status ?? 'belum diisi').') tidak memungkinkan pengisian KRS. Silakan hubungi bagian akademik.');
        }

        // Kunci baris mahasiswa dan kelas agar dua pengambilan bersamaan tidak melewati kapasitas/SKS.
        $error = DB::transaction(function () use ($mahasiswa, $kelasKuliah): ?string {
            MahasiswaProfile::query()->whereKey($mahasiswa->id)->lockForUpdate()->first();
            $kelas = KelasKuliah::query()->whereKey($kelasKuliah->id)->lockForUpdate()->first();
            $tawaran = new TawaranKrs($mahasiswa, $kelasKuliah->tahunAkademik, $this->semuaKrs($mahasiswa));
            $alasan = $tawaran->alasanTidakBolehAmbil($kelasKuliah->mataKuliah);

            if ($alasan !== null) {
                return $alasan;
            }

            $bentrok = Jadwal::bentrokUntukMahasiswa($kelas, $mahasiswa->id);

            if ($bentrok !== null) {
                return 'Jadwal kelas ini bentrok dengan kelas '.$bentrok->keterangan().'.';
            }

            // Kelas TA/Skripsi tidak dibatasi kapasitas: tiap mahasiswa dibimbing terpisah.
            if (! $kelasKuliah->mataKuliah->tugas_akhir && $kelas->krs()->count() >= $kelas->kapasitas) {
                return 'Kelas sudah penuh, silakan ambil kelas lain.';
            }

            $sksDiambil = (int) $mahasiswa->krs()
                ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'krs.kelas_id')
                ->join('mata_kuliahs', 'mata_kuliahs.id', '=', 'kelas_kuliah.matkul_id')
                ->where('kelas_kuliah.tahun_akademik_id', $kelas->tahun_akademik_id)
                ->sum('mata_kuliahs.sks');

            $maksSks = PengaturanAkademik::maksSksUntuk($mahasiswa->ipsSemesterSebelum($kelasKuliah->tahunAkademik)['ips'] ?? null, $mahasiswa->prodi_id);

            if ($sksDiambil + $kelasKuliah->mataKuliah->sks > $maksSks) {
                return "Total SKS melebihi batas maksimal {$maksSks} SKS untuk Anda (sudah diambil {$sksDiambil} SKS).";
            }

            Krs::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

            return null;
        });

        return $error === null
            ? back()->with('krs_success', 'Kelas berhasil diambil.')
            : back()->with('krs_error', $error);
    }

    /**
     * Batalkan kelas yang sudah diambil, hanya selama KRS masih bisa diubah dan sebelum ada nilai.
     */
    public function destroy(Request $request, Krs $krs): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        abort_unless($krs->mahasiswa_id === $mahasiswa->id, 403);
        $tahunAkademik = $krs->kelasKuliah?->tahunAkademik;

        if (($alasan = $this->alasanTidakBolehUbah($mahasiswa, $tahunAkademik)) !== null) {
            return back()->with('krs_error', $alasan);
        }

        if (filled($krs->nilai)) {
            return back()->with('krs_error', 'Kelas yang sudah memiliki nilai tidak dapat dibatalkan.');
        }

        $krs->cancel();

        return back()->with('krs_success', 'Kelas berhasil dibatalkan.');
    }

    /**
     * Simpan (kunci) KRS semester berjalan. Setelah ini mahasiswa tidak bisa menambah atau
     * membatalkan kelas sendiri; perubahan hanya lewat pengajuan pindah kelas atau admin.
     * Saat verifikasi KRS aktif, KRS berstatus diajukan sampai disetujui atau dikembalikan admin.
     */
    public function simpan(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        $tahunAkademik = TahunAkademik::aktif();

        if (($alasan = KrsSemester::alasanTidakBolehUbah($mahasiswa->id, $tahunAkademik)) !== null) {
            return back()->with('krs_error', $alasan);
        }

        $krsTahunIni = $this->semuaKrs($mahasiswa)
            ->filter(fn (Krs $krs): bool => $krs->kelasKuliah?->tahun_akademik_id === $tahunAkademik->id);

        if ($krsTahunIni->isEmpty()) {
            return back()->with('krs_error', 'Ambil minimal satu kelas sebelum menyimpan KRS.');
        }

        $sksDiambil = $krsTahunIni->sum(fn (Krs $krs): int => $krs->kelasKuliah?->mataKuliah?->sks ?? 0);
        $maksSks = PengaturanAkademik::maksSksUntuk($mahasiswa->ipsSemesterSebelum($tahunAkademik)['ips'] ?? null, $mahasiswa->prodi_id);

        // Mengambil SKS di bawah batas boleh saja (mis. semester akhir), tetapi harus disadari
        // karena sesudah disimpan KRS tidak bisa ditambah sendiri.
        if ($sksDiambil < $maksSks && ! $request->boolean('konfirmasi')) {
            return back()->with('krs_konfirmasi', "Anda baru mengambil {$sksDiambil} dari {$maksSks} SKS yang menjadi jatah Anda. Tambah kelas lagi, atau simpan bila sisa mata kuliah Anda memang tinggal ini.");
        }

        $kunci = KrsSemester::simpan($mahasiswa->id, $tahunAkademik->id);

        return back()->with('krs_success', $kunci->status === KrsSemester::DIAJUKAN
            ? 'KRS berhasil diajukan dan menunggu verifikasi admin.'
            : 'KRS berhasil disimpan dan dikunci.');
    }

    /**
     * KRS (PDF) untuk tahun akademik aktif, atau tahun akademik yang dipilih.
     */
    public function download(Request $request): HttpResponse
    {
        $mahasiswa = $this->mahasiswa($request)->muatPengesahan();
        $tahunAkademik = $request->filled('tahun_akademik_id')
            ? TahunAkademik::find($request->integer('tahun_akademik_id'))
            : TahunAkademik::aktif();
        abort_if($tahunAkademik === null, 404);

        $krs = $mahasiswa->krs()
            ->whereHas('kelasKuliah', fn ($query) => $query->where('tahun_akademik_id', $tahunAkademik->id))
            ->with([
                'kelasKuliah:id,matkul_id,dosen_id,kode_kelas',
                'kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul,sks,jenis',
                'kelasKuliah.dosen:id,user_id',
                'kelasKuliah.dosen.user:id,name',
                'kelasKuliah.jadwals' => fn ($query) => $query->with('ruang:id,kode_ruang')->orderBy('jam_mulai'),
            ])
            ->get()
            ->sortBy(fn (Krs $item): string => (string) $item->kelasKuliah?->mataKuliah?->kode_matkul)
            ->values();
        abort_if($krs->isEmpty(), 404, 'Belum ada kelas yang diambil pada tahun akademik ini.');

        $ipsSebelumnya = $mahasiswa->ipsSemesterSebelum($tahunAkademik, $this->semuaKrs($mahasiswa));
        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView('pdf.krs', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'mahasiswa' => $mahasiswa,
            'tahunAkademik' => $tahunAkademik,
            'krs' => $krs,
            'ipsSebelumnya' => $ipsSebelumnya['ips'] ?? null,
            'maksSks' => PengaturanAkademik::maksSksUntuk($ipsSebelumnya['ips'] ?? null, $mahasiswa->prodi_id),
            'kunci' => KrsSemester::untuk($mahasiswa->id, $tahunAkademik->id),
        ])->download('krs-'.$mahasiswa->nim.'-'.Str::slug($tahunAkademik->tahun.'-'.$tahunAkademik->semester).'.pdf');
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }

    private function periodeKrsAktif(?TahunAkademik $tahunAkademik): bool
    {
        return $tahunAkademik?->periodeKrsAktif() === true;
    }

    /**
     * Alasan KRS tidak bisa diubah sendiri, ditambah arahan pindah kelas bila KRS sudah terkunci.
     */
    private function alasanTidakBolehUbah(MahasiswaProfile $mahasiswa, ?TahunAkademik $tahunAkademik): ?string
    {
        $alasan = KrsSemester::alasanTidakBolehUbah($mahasiswa->id, $tahunAkademik);

        if ($alasan !== null && Feature::aktif('pindah_kelas') && KrsSemester::disetujui($mahasiswa->id, (int) $tahunAkademik?->id)) {
            return 'KRS Anda sudah disimpan dan terkunci. Gunakan form pindah kelas, atau hubungi admin bila perlu membukanya.';
        }

        return $alasan;
    }

    /**
     * Seluruh KRS mahasiswa beserta data kelas yang dibutuhkan halaman KRS.
     *
     * @return Collection<int, Krs>
     */
    private function semuaKrs(MahasiswaProfile $mahasiswa): Collection
    {
        return $mahasiswa->krs()
            ->with([
                'kelasKuliah:id,matkul_id,tahun_akademik_id',
                'kelasKuliah.mataKuliah:id,prodi_id,sks,tugas_akhir',
                'kelasKuliah.tahunAkademik:id,tahun,semester,tanggal_mulai,status',
            ])
            ->get();
    }
}
