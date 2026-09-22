<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\TahunAkademik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class KrsController extends Controller
{
    public function index(Request $request): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;

        abort_if($mahasiswa === null, 403);

        $tahunAkademik = TahunAkademik::where('status', true)->first();
        $today = Carbon::today();
        $periodeKrsAktif = $tahunAkademik !== null
            && $today->between(
                Carbon::parse($tahunAkademik->tanggal_krs_awal),
                Carbon::parse($tahunAkademik->tanggal_krs_akhir),
            );

        $kelasKuliahs = KelasKuliah::query()
            ->when(! $periodeKrsAktif, fn ($query) => $query->whereKey(0))
            ->with([
                'mataKuliah.prodi',
                'tahunAkademik',
                'dosen.user',
                'jadwals' => fn ($query) => $query->with('ruang')->orderBy('jam_mulai'),
            ])
            ->withCount('krs')
            ->whereHas('mataKuliah', fn ($query) => $query->where('prodi_id', $mahasiswa->prodi_id)->where('semester', $mahasiswa->semester))
            ->whereHas('tahunAkademik', fn ($query) => $query->where('status', true))
            ->orderBy('kode_kelas')
            ->get();

        return Inertia::render('Mahasiswa/Krs', [
            'kelasKuliahs' => $kelasKuliahs,
            'mahasiswa' => $mahasiswa->only(['semester', 'angkatan', 'prodi_id']),
            'kelasDiambil' => $mahasiswa->krs()->pluck('kelas_id')->values(),
            'tahunAkademik' => $tahunAkademik,
            'periodeKrsAktif' => $periodeKrsAktif,
        ]);
    }

    public function store(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $mahasiswa = $request->user()->mahasiswaProfile;

        abort_if($mahasiswa === null, 403);

        $kelasKuliah->loadMissing('mataKuliah', 'tahunAkademik');

        abort_unless(
            $kelasKuliah->mataKuliah?->prodi_id === $mahasiswa->prodi_id
                && $kelasKuliah->mataKuliah?->semester === $mahasiswa->semester
                && $kelasKuliah->tahunAkademik?->status === true,
            404,
        );

        $alreadyTaken = $mahasiswa->krs()
            ->whereHas('kelasKuliah', fn ($query) => $query->where('matkul_id', $kelasKuliah->matkul_id))
            ->exists();

        if ($alreadyTaken) {
            return back()->with('krs_error', 'Anda sudah mengambil kelas di mata kuliah ini. Silakan isi form pindah kelas apabila ingin pindah kelas.');
        }

        $bentrok = $this->jadwalBentrok($mahasiswa, $kelasKuliah);

        if ($bentrok !== null) {
            return back()->with('krs_error', 'Jadwal kelas ini bentrok dengan kelas '.$bentrok->kelasKuliah?->kode_kelas.' ('.$bentrok->kelasKuliah?->mataKuliah?->nama_matkul.') pada '.$bentrok->hari.', '.substr($bentrok->jam_mulai, 0, 5).'–'.substr($bentrok->jam_akhir, 0, 5).'.');
        }

        if ($kelasKuliah->krs()->count() >= $kelasKuliah->kapasitas) {
            return back()->with('krs_error', 'Kelas sudah penuh, silakan ambil kelas lain.');
        }

        Krs::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelasKuliah->id, 'status' => 'Aktif']);

        return back()->with('krs_success', 'Kelas berhasil diambil.');
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
