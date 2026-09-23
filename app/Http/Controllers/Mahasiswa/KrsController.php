<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanAkademik;
use App\Models\SkalaNilai;
use App\Models\TahunAkademik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KrsController extends Controller
{
    public function index(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $tahunAkademik = TahunAkademik::where('status', true)->first();
        $periodeKrsAktif = $this->periodeKrsAktif($tahunAkademik);
        // Seluruh KRS mahasiswa dimuat sekali, lalu dipakai untuk riwayat, IPS, dan ringkasan SKS.
        $semuaKrs = $this->semuaKrs($mahasiswa);
        $riwayat = $this->riwayatMatkul($mahasiswa, $semuaKrs);
        $matkulMengulang = $riwayat->filter(fn (Collection $krs, int $matkulId): bool => $this->alasanTidakBolehAmbil($riwayat, $matkulId, $tahunAkademik) === null)
            ->keys()
            ->values();

        $kelasKuliahs = KelasKuliah::query()
            ->when(! $periodeKrsAktif, fn ($query) => $query->whereKey(0))
            ->with([
                'mataKuliah.prodi',
                'tahunAkademik',
                'dosen:id,user_id',
                'dosen.user:id,name',
                'jadwals' => fn ($query) => $query->with('ruang')->orderBy('jam_mulai'),
            ])
            ->withCount('krs')
            ->whereHas('mataKuliah', fn ($query) => $query
                ->where('prodi_id', $mahasiswa->prodi_id)
                ->where(fn ($query) => $query->where('semester', $mahasiswa->semester)->orWhereIn('id', $matkulMengulang)))
            ->whereHas('tahunAkademik', fn ($query) => $query->where('status', true))
            ->orderBy('kode_kelas')
            ->get();

        $ipsSebelumnya = $mahasiswa->ipsSemesterSebelum($tahunAkademik, $semuaKrs);
        $krsTahunIni = $tahunAkademik === null
            ? collect()
            : $semuaKrs->filter(fn (Krs $krs): bool => $krs->kelasKuliah?->tahun_akademik_id === $tahunAkademik->id);

        return Inertia::render('Mahasiswa/Krs', [
            'kelasKuliahs' => $kelasKuliahs,
            'mahasiswa' => $mahasiswa->only(['semester', 'angkatan', 'prodi_id', 'status']),
            'kelasDiambil' => $semuaKrs->pluck('kelas_id')->values(),
            'krsTahunIni' => $krsTahunIni->map(fn (Krs $krs): array => ['id' => $krs->id, 'kelas_id' => $krs->kelas_id, 'nilai' => $krs->nilai])->values(),
            'matkulMengulang' => $matkulMengulang,
            'sksDiambil' => $krsTahunIni->sum(fn (Krs $krs): int => $krs->kelasKuliah?->mataKuliah?->sks ?? 0),
            'maksSks' => PengaturanAkademik::maksSksUntuk($ipsSebelumnya['ips'] ?? null),
            'ipsSebelumnya' => $ipsSebelumnya === null ? null : [
                'ips' => $ipsSebelumnya['ips'],
                'tahun_akademik' => $ipsSebelumnya['tahun_akademik']->tahun.' '.$ipsSebelumnya['tahun_akademik']->semester,
            ],
            'bolehKrs' => in_array($mahasiswa->status, Krs::STATUS_MAHASISWA_BOLEH_KRS, true),
            'tahunAkademik' => $tahunAkademik,
            'periodeKrsAktif' => $periodeKrsAktif,
        ]);
    }

    public function store(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        $kelasKuliah->loadMissing('mataKuliah', 'tahunAkademik');

        abort_unless($kelasKuliah->mataKuliah?->prodi_id === $mahasiswa->prodi_id && $kelasKuliah->tahunAkademik?->status === true, 404);

        if (! $this->periodeKrsAktif($kelasKuliah->tahunAkademik)) {
            return back()->with('krs_error', 'Periode pengambilan KRS belum dibuka atau sudah berakhir.');
        }

        if (! in_array($mahasiswa->status, Krs::STATUS_MAHASISWA_BOLEH_KRS, true)) {
            return back()->with('krs_error', 'Status akademik Anda ('.($mahasiswa->status ?? 'belum diisi').') tidak memungkinkan pengisian KRS. Silakan hubungi bagian akademik.');
        }

        // Kunci baris mahasiswa dan kelas agar dua pengambilan bersamaan tidak melewati kapasitas/SKS.
        $error = DB::transaction(function () use ($mahasiswa, $kelasKuliah): ?string {
            MahasiswaProfile::query()->whereKey($mahasiswa->id)->lockForUpdate()->first();
            $kelas = KelasKuliah::query()->whereKey($kelasKuliah->id)->lockForUpdate()->first();
            // Seluruh KRS mahasiswa dimuat sekali, lalu dipakai untuk riwayat, IPS, dan ringkasan SKS.
        $semuaKrs = $this->semuaKrs($mahasiswa);
        $riwayat = $this->riwayatMatkul($mahasiswa, $semuaKrs);
            $alasan = $this->alasanTidakBolehAmbil($riwayat, $kelas->matkul_id, $kelasKuliah->tahunAkademik);

            if ($alasan !== null) {
                return $alasan;
            }

            if ($kelasKuliah->mataKuliah->semester !== (int) $mahasiswa->semester && ! $riwayat->has($kelas->matkul_id)) {
                return 'Mata kuliah ini bukan untuk semester Anda.';
            }

            $bentrok = $this->jadwalBentrok($mahasiswa, $kelas);

            if ($bentrok !== null) {
                return 'Jadwal kelas ini bentrok dengan kelas '.$bentrok->kelasKuliah?->kode_kelas.' ('.$bentrok->kelasKuliah?->mataKuliah?->nama_matkul.') pada '.$bentrok->hari.', '.substr($bentrok->jam_mulai, 0, 5).'–'.substr($bentrok->jam_akhir, 0, 5).'.';
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

        if (filled($krs->nilai)) {
            return back()->with('krs_error', 'Kelas yang sudah memiliki nilai tidak dapat dibatalkan.');
        }

        $krs->cancel();

        return back()->with('krs_success', 'Kelas berhasil dibatalkan.');
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }

    private function periodeKrsAktif(?TahunAkademik $tahunAkademik): bool
    {
        return $tahunAkademik !== null
            && $tahunAkademik->tanggal_krs_awal !== null
            && $tahunAkademik->tanggal_krs_akhir !== null
            && Carbon::today()->between(
                Carbon::parse($tahunAkademik->tanggal_krs_awal)->startOfDay(),
                Carbon::parse($tahunAkademik->tanggal_krs_akhir)->endOfDay(),
            );
    }

    /**
     * Riwayat KRS mahasiswa dikelompokkan per mata kuliah.
     *
     * @return Collection<int, Collection<int, Krs>>
     */
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

    /**
     * @param  Collection<int, Krs>|null  $krsTerpakai
     * @return Collection<int, Collection<int, Krs>>
     */
    private function riwayatMatkul(MahasiswaProfile $mahasiswa, ?Collection $krsTerpakai = null): Collection
    {
        return ($krsTerpakai ?? $this->semuaKrs($mahasiswa))
            ->filter(fn (Krs $krs): bool => $krs->kelasKuliah !== null)
            ->groupBy(fn (Krs $krs): int => $krs->kelasKuliah->matkul_id);
    }

    /**
     * Alasan mata kuliah tidak boleh diambil tahun ini, atau null bila boleh (termasuk mengulang).
     *
     * @param  Collection<int, Collection<int, Krs>>  $riwayat
     */
    private function alasanTidakBolehAmbil(Collection $riwayat, int $matkulId, ?TahunAkademik $tahunAkademik): ?string
    {
        foreach ($riwayat->get($matkulId, collect()) as $krs) {
            if ($krs->kelasKuliah->tahun_akademik_id === $tahunAkademik?->id) {
                return 'Anda sudah mengambil kelas di mata kuliah ini. Silakan isi form pindah kelas apabila ingin pindah kelas.';
            }

            if (blank($krs->nilai)) {
                return 'Mata kuliah ini masih menunggu nilai dari pengambilan sebelumnya.';
            }

            if (! SkalaNilai::bolehDiulang($krs->nilai)) {
                return "Anda sudah lulus mata kuliah ini dengan nilai {$krs->nilai}.";
            }
        }

        return null;
    }

    /**
     * Jadwal kelas lain di KRS mahasiswa (tahun akademik yang sama) yang beririsan dengan jadwal kelas ini.
     */
    private function jadwalBentrok(MahasiswaProfile $mahasiswa, KelasKuliah $kelasKuliah): ?Jadwal
    {
        foreach ($kelasKuliah->jadwals()->get() as $jadwal) {
            $bentrok = Jadwal::query()
                ->overlapping($jadwal->hari, $jadwal->jam_mulai, $jadwal->jam_akhir)
                ->inTahunAkademik($kelasKuliah->tahun_akademik_id)
                ->whereIn('kelas_id', $mahasiswa->krs()->select('kelas_id'))
                ->with('kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul')
                ->first();

            if ($bentrok !== null) {
                return $bentrok;
            }
        }

        return null;
    }
}
