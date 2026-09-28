<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\PengaturanAkademik;
use App\Models\TahunAkademik;
use App\TawaranKrs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KrsController extends Controller
{
    public function index(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $tahunAkademik = TahunAkademik::aktif();
        $periodeKrsAktif = $this->periodeKrsAktif($tahunAkademik);
        // Seluruh KRS mahasiswa dimuat sekali, lalu dipakai untuk tawaran, IPS, dan ringkasan SKS.
        $semuaKrs = $this->semuaKrs($mahasiswa);
        $tawaran = new TawaranKrs($mahasiswa, $tahunAkademik, $semuaKrs);
        $kelasDiambil = $semuaKrs->pluck('kelas_id');

        $kelasKuliahs = KelasKuliah::query()
            ->when(! $periodeKrsAktif, fn ($query) => $query->whereKey(0))
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
            'maksSks' => PengaturanAkademik::maksSksUntuk($ipsSebelumnya['ips'] ?? null),
            'ipsSebelumnya' => $ipsSebelumnya === null ? null : [
                'ips' => $ipsSebelumnya['ips'],
                'tahun_akademik' => $ipsSebelumnya['tahun_akademik']->tahun.' '.$ipsSebelumnya['tahun_akademik']->semester,
            ],
            'bolehKrs' => in_array($mahasiswa->status, Krs::STATUS_MAHASISWA_BOLEH_KRS, true),
            'tahunAkademik' => $tahunAkademik,
            'periodeKrsAktif' => $periodeKrsAktif,
            'krsTersimpan' => $tahunAkademik !== null && KrsSemester::tersimpan($mahasiswa->id, $tahunAkademik->id),
            'krsDisimpanPada' => $tahunAkademik === null ? null : KrsSemester::query()
                ->where('mahasiswa_id', $mahasiswa->id)
                ->where('tahun_akademik_id', $tahunAkademik->id)
                ->value('disimpan_pada'),
        ]);
    }

    public function store(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        $kelasKuliah->loadMissing('mataKuliah.prasyarat:mata_kuliahs.id,nama_matkul', 'tahunAkademik');

        abort_unless($kelasKuliah->mataKuliah?->prodi_id === $mahasiswa->prodi_id && $kelasKuliah->tahunAkademik?->status === true, 404);

        if (! $this->periodeKrsAktif($kelasKuliah->tahunAkademik)) {
            return back()->with('krs_error', 'Periode pengambilan KRS belum dibuka atau sudah berakhir.');
        }

        if (KrsSemester::tersimpan($mahasiswa->id, $kelasKuliah->tahun_akademik_id)) {
            return back()->with('krs_error', 'KRS Anda sudah disimpan dan terkunci. Gunakan form pindah kelas, atau hubungi admin bila perlu membukanya.');
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

            if ($kelas->krs()->count() >= $kelas->kapasitas) {
                return 'Kelas sudah penuh, silakan ambil kelas lain.';
            }

            $sksDiambil = (int) $mahasiswa->krs()
                ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'krs.kelas_id')
                ->join('mata_kuliahs', 'mata_kuliahs.id', '=', 'kelas_kuliah.matkul_id')
                ->where('kelas_kuliah.tahun_akademik_id', $kelas->tahun_akademik_id)
                ->sum('mata_kuliahs.sks');

            $maksSks = PengaturanAkademik::maksSksUntuk($mahasiswa->ipsSemesterSebelum($kelasKuliah->tahunAkademik)['ips'] ?? null);

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
     * Batalkan kelas yang sudah diambil, hanya selama periode KRS dan sebelum ada nilai.
     */
    public function destroy(Request $request, Krs $krs): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        abort_unless($krs->mahasiswa_id === $mahasiswa->id, 403);
        $tahunAkademik = $krs->kelasKuliah?->tahunAkademik;

        if ($tahunAkademik?->status !== true || ! $this->periodeKrsAktif($tahunAkademik)) {
            return back()->with('krs_error', 'Kelas hanya dapat dibatalkan selama periode pengambilan KRS.');
        }

        if (KrsSemester::tersimpan($mahasiswa->id, $tahunAkademik->id)) {
            return back()->with('krs_error', 'KRS Anda sudah disimpan dan terkunci, kelas tidak dapat dibatalkan sendiri.');
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
     */
    public function simpan(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        $tahunAkademik = TahunAkademik::where('status', true)->first();

        if (! $this->periodeKrsAktif($tahunAkademik)) {
            return back()->with('krs_error', 'Periode pengambilan KRS belum dibuka atau sudah berakhir.');
        }

        if (KrsSemester::tersimpan($mahasiswa->id, $tahunAkademik->id)) {
            return back()->with('krs_error', 'KRS Anda sudah tersimpan sebelumnya.');
        }

        $krsTahunIni = $this->semuaKrs($mahasiswa)
            ->filter(fn (Krs $krs): bool => $krs->kelasKuliah?->tahun_akademik_id === $tahunAkademik->id);

        if ($krsTahunIni->isEmpty()) {
            return back()->with('krs_error', 'Ambil minimal satu kelas sebelum menyimpan KRS.');
        }

        $sksDiambil = $krsTahunIni->sum(fn (Krs $krs): int => $krs->kelasKuliah?->mataKuliah?->sks ?? 0);
        $maksSks = PengaturanAkademik::maksSksUntuk($mahasiswa->ipsSemesterSebelum($tahunAkademik)['ips'] ?? null);

        // Mengambil SKS di bawah batas boleh saja (mis. semester akhir), tetapi harus disadari
        // karena sesudah disimpan KRS tidak bisa ditambah sendiri.
        if ($sksDiambil < $maksSks && ! $request->boolean('konfirmasi')) {
            return back()->with('krs_konfirmasi', "Anda baru mengambil {$sksDiambil} dari {$maksSks} SKS yang menjadi jatah Anda. Tambah kelas lagi, atau simpan bila sisa mata kuliah Anda memang tinggal ini.");
        }

        KrsSemester::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $tahunAkademik->id,
            'disimpan_pada' => now(),
        ]);

        return back()->with('krs_success', 'KRS berhasil disimpan dan dikunci.');
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
     * Seluruh KRS mahasiswa beserta data kelas yang dibutuhkan halaman KRS.
     *
     * @return Collection<int, Krs>
     */
    private function semuaKrs(MahasiswaProfile $mahasiswa): Collection
    {
        return $mahasiswa->krs()
            ->with([
                'kelasKuliah:id,matkul_id,tahun_akademik_id',
                'kelasKuliah.mataKuliah:id,sks',
                'kelasKuliah.tahunAkademik:id,tahun,semester,tanggal_mulai',
            ])
            ->get();
    }
}
