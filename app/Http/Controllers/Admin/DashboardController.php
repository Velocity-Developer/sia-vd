<?php

namespace App\Http\Controllers\Admin;

use App\Feature;
use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\KelasKuliah;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanAkademik;
use App\Models\PengajuanPindahKelas;
use App\Models\PengajuanSusulan;
use App\Models\Pertemuan;
use App\Models\ProgramStudi;
use App\Models\TagihanRemidi;
use App\Models\TagihanSemester;
use App\Models\TagihanSusulan;
use App\Models\TahunAkademik;
use App\Models\User;
use App\PengingatCuti;
use App\PengingatTugasAkhir;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard admin/karyawan: angka utama tahun akademik aktif dan daftar pekerjaan yang menunggu.
 * Setiap bagian (termasuk tiap kartu statistik) hanya dikirim bila role pengguna punya izin halaman
 * sumber datanya, sehingga role karyawan seperti keuangan hanya melihat ringkasan bidangnya.
 */
class DashboardController extends Controller
{
    /** Izin yang menghasilkan butir "Perlu ditindaklanjuti"; tanpa satu pun, bagian itu disembunyikan. */
    private const IZIN_TINDAKAN = ['admin.tagihan', 'admin.verifikasi-krs', 'admin.pengajuan-akademik', 'admin.pengajuan-cuti', 'admin.ujian', 'admin.pindah-kelas'];

    private const IZIN_MASA_KRS = ['admin.tahun-akademik', 'admin.kelas-kuliah', 'admin.users.mahasiswa'];

    private const IZIN_SEBARAN_PRODI = ['admin.users.mahasiswa', 'admin.program-studi'];

    /**
     * Izin yang membuka tiap kartu statistik (cukup salah satu).
     *
     * @var array<string, list<string>>
     */
    private const IZIN_STATISTIK = [
        'mahasiswa_aktif' => ['admin.users.mahasiswa'],
        'dosen_aktif' => ['admin.users.dosen'],
        'kelas_kuliah' => ['admin.kelas-kuliah'],
        'program_studi' => ['admin.program-studi'],
        'mahasiswa_cuti' => ['admin.users.mahasiswa', 'admin.pengajuan-cuti'],
        'mahasiswa_lulus' => ['admin.users.mahasiswa', 'admin.pengajuan-akademik'],
    ];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $tahunAkademik = TahunAkademik::aktif();
        $bolehKrs = $this->bolehSalahSatu($user, self::IZIN_MASA_KRS);

        return Inertia::render('Admin/Dashboard', [
            'tahunAkademik' => $tahunAkademik === null ? null : [
                'label' => $tahunAkademik->label(),
                'tanggal_mulai' => $tahunAkademik->tanggal_mulai?->toDateString(),
                'tanggal_akhir' => $tahunAkademik->tanggal_akhir?->toDateString(),
                'tanggal_krs_awal' => $bolehKrs && $tahunAkademik->tanggal_krs_awal ? Carbon::parse($tahunAkademik->tanggal_krs_awal)->toDateString() : null,
                'tanggal_krs_akhir' => $bolehKrs && $tahunAkademik->tanggal_krs_akhir ? Carbon::parse($tahunAkademik->tanggal_krs_akhir)->toDateString() : null,
            ],
            'statistik' => $this->statistik($user, $tahunAkademik),
            'tindakan' => $this->bolehSalahSatu($user, self::IZIN_TINDAKAN) ? $this->tindakan($user, $tahunAkademik) : null,
            'tagihan' => $user->hasPermission('admin.tagihan') && $tahunAkademik
                ? [...TagihanSemester::ringkasan($tahunAkademik->id), 'tautan' => route('admin.tagihan.index', ['tahun_akademik_id' => $tahunAkademik->id])]
                : null,
            'buktiTerbaru' => $user->hasPermission('admin.tagihan') ? $this->buktiTerbaru() : null,
            'perkuliahanHariIni' => $user->hasPermission('admin.presensi') && $tahunAkademik ? $this->perkuliahanHariIni($tahunAkademik) : null,
            'mahasiswaPerProdi' => $this->bolehSalahSatu($user, self::IZIN_SEBARAN_PRODI) ? $this->mahasiswaPerProdi() : null,
            'pengingatTugasAkhir' => $user->hasPermission('admin.pengajuan-akademik') ? PengingatTugasAkhir::untukAdmin() : null,
        ]);
    }

    /**
     * @param  list<string>  $izin
     */
    private function bolehSalahSatu(User $user, array $izin): bool
    {
        return array_intersect($izin, $user->permissionKeys()) !== [];
    }

    /**
     * Hanya kartu yang diizinkan untuk role pengguna yang dikirim (dan dihitung), urut sesuai IZIN_STATISTIK.
     *
     * @return array<string, int>
     */
    private function statistik(User $user, ?TahunAkademik $tahunAkademik): array
    {
        $kunci = array_keys(array_filter(self::IZIN_STATISTIK, fn (array $izin): bool => $this->bolehSalahSatu($user, $izin)));
        $mahasiswa = array_intersect($kunci, ['mahasiswa_aktif', 'mahasiswa_cuti', 'mahasiswa_lulus']) === [] ? collect()
            : MahasiswaProfile::query()->whereHas('user')->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return collect($kunci)->mapWithKeys(fn (string $k): array => [$k => match ($k) {
            'mahasiswa_aktif' => (int) collect(MahasiswaProfile::STATUS_AKTIF)->sum(fn (string $status): int => (int) ($mahasiswa[$status] ?? 0)),
            'mahasiswa_cuti' => (int) ($mahasiswa['Cuti'] ?? 0),
            'mahasiswa_lulus' => (int) ($mahasiswa['Lulus'] ?? 0),
            'dosen_aktif' => DosenProfile::query()->whereHas('user')->where('status', 'Aktif')->count(),
            'kelas_kuliah' => $tahunAkademik === null ? 0 : KelasKuliah::query()->where('tahun_akademik_id', $tahunAkademik->id)->count(),
            'program_studi' => ProgramStudi::query()->count(),
        }])->all();
    }

    /**
     * Lima bukti bayar terbaru yang menunggu verifikasi dari tagihan semester, remidi, dan susulan.
     *
     * @return list<array{jenis: string, mahasiswa: string|null, nim: string|null, keterangan: string|null, total: int, diunggah: string|null, tautan: string}>
     */
    private function buktiTerbaru(): array
    {
        $mhs = ['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name'];
        $kelas = ['kelasKuliah:id,kode_kelas,matkul_id,tahun_akademik_id', 'kelasKuliah.mataKuliah:id,nama_matkul'];
        $baris = fn (string $jenis, $t, ?string $keterangan, string $rute, int|string|null $taId): array => [
            'jenis' => $jenis,
            'mahasiswa' => $t->mahasiswa?->user?->name,
            'nim' => $t->mahasiswa?->nim,
            'keterangan' => $keterangan,
            'total' => (int) $t->total,
            'diunggah' => $t->bukti_diunggah_at?->toIso8601String(),
            'tautan' => route($rute, ['tahun_akademik_id' => $taId, 'status' => TagihanSemester::MENUNGGU]),
        ];
        $terbaru = fn (Builder $q) => $q->where('status', TagihanSemester::MENUNGGU)->latest('bukti_diunggah_at')->limit(5)->get();

        return collect()
            ->concat($terbaru(TagihanSemester::query()->with([...$mhs, 'tahunAkademik']))
                ->map(fn (TagihanSemester $t) => $baris('Semester', $t, $t->tahunAkademik?->label(), 'admin.tagihan.index', $t->tahun_akademik_id)))
            ->concat($terbaru(TagihanRemidi::query()->with([...$mhs, ...$kelas]))
                ->map(fn (TagihanRemidi $t) => $baris('Remidi', $t, $t->kelasKuliah?->mataKuliah?->nama_matkul, 'admin.tagihan-remidi.index', $t->kelasKuliah?->tahun_akademik_id)))
            ->concat(! Feature::aktif('ujian_susulan') ? [] : $terbaru(TagihanSusulan::query()->with([...$mhs, ...$kelas]))
                ->map(fn (TagihanSusulan $t) => $baris('Susulan', $t, $t->kelasKuliah?->mataKuliah?->nama_matkul, 'admin.tagihan-susulan.index', $t->kelasKuliah?->tahun_akademik_id)))
            ->sortByDesc('diunggah')->take(5)->values()->all();
    }

    /**
     * Pekerjaan yang menunggu keputusan admin. Yang terikat tahun akademik dipecah per tahun agar tautannya
     * membuka daftar dengan filter yang sama dengan angka yang ditampilkan.
     *
     * @return list<array{judul: string, keterangan: string|null, jumlah: int, tautan: string, penting: bool}>
     */
    private function tindakan(User $user, ?TahunAkademik $tahunAkademik): array
    {
        $daftar = collect();
        $tahun = TahunAkademik::query()->get()->keyBy('id');
        $label = fn (int|string|null $id): ?string => $tahun->get($id)?->label();

        if ($user->hasPermission('admin.tagihan')) {
            $this->perTahun(TagihanSemester::query()->where('status', TagihanSemester::MENUNGGU), 'tahun_akademik_id')
                ->each(fn (int $jumlah, int|string $taId) => $daftar->push($this->butir('Bukti bayar tagihan semester', $label($taId), $jumlah,
                    route('admin.tagihan.index', ['tahun_akademik_id' => $taId, 'status' => TagihanSemester::MENUNGGU]), true)));
            $this->perTahun(TagihanRemidi::query()->where('tagihan_remidi.status', TagihanRemidi::MENUNGGU)->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'tagihan_remidi.kelas_id'))
                ->each(fn (int $jumlah, int|string $taId) => $daftar->push($this->butir('Bukti bayar tagihan remidi', $label($taId), $jumlah,
                    route('admin.tagihan-remidi.index', ['tahun_akademik_id' => $taId, 'status' => TagihanRemidi::MENUNGGU]), true)));
            $susulan = Feature::aktif('ujian_susulan') ? TagihanSusulan::query() : TagihanSusulan::query()->whereRaw('1 = 0');
            $this->perTahun($susulan->where('tagihan_susulan.status', TagihanSusulan::MENUNGGU)->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'tagihan_susulan.kelas_id'))
                ->each(fn (int $jumlah, int|string $taId) => $daftar->push($this->butir('Bukti bayar tagihan susulan', $label($taId), $jumlah,
                    route('admin.tagihan-susulan.index', ['tahun_akademik_id' => $taId, 'status' => TagihanSusulan::MENUNGGU]), true)));

            if ($tahunAkademik !== null) {
                $belumTerbit = TagihanSemester::ringkasan($tahunAkademik->id)['belum_terbit'];
                if ($belumTerbit > 0) {
                    $daftar->push($this->butir('Mahasiswa aktif belum ditagih', $tahunAkademik->label(), $belumTerbit,
                        route('admin.tagihan.index', ['tahun_akademik_id' => $tahunAkademik->id, 'status' => 'belum_terbit']), false));
                }
            }
        }

        if ($user->hasPermission('admin.verifikasi-krs')) {
            $this->perTahun(KrsSemester::query()->where('status', KrsSemester::DIAJUKAN), 'tahun_akademik_id')
                ->each(fn (int $jumlah, int|string $taId) => $daftar->push($this->butir('KRS menunggu verifikasi', $label($taId), $jumlah,
                    route('admin.verifikasi-krs.index', ['tahun_akademik_id' => $taId]), true)));
        }

        if ($user->hasPermission('admin.pengajuan-akademik')) {
            $this->perJenis(PengajuanAkademik::JENIS)->each(fn (int $jumlah, string $jenis) => $daftar->push($this->butir(
                'Pengajuan '.strtolower(PengajuanAkademik::LABEL_JENIS[$jenis]), null, $jumlah,
                route('admin.pengajuan-akademik.index', ['jenis' => $jenis, 'status' => PengajuanAkademik::MENUNGGU]), true)));
        }

        if ($user->hasPermission('admin.pengajuan-cuti')) {
            $this->perJenis(PengajuanAkademik::JENIS_CUTI)->each(fn (int $jumlah, string $jenis) => $daftar->push($this->butir(
                'Pengajuan '.strtolower(PengajuanAkademik::LABEL_JENIS[$jenis]), null, $jumlah,
                route('admin.pengajuan-cuti.index', ['jenis' => $jenis, 'status' => PengajuanAkademik::MENUNGGU]), true)));

            $cutiBerakhir = PengingatCuti::jumlahCutiBerakhir();
            if ($cutiBerakhir > 0) {
                $daftar->push($this->butir('Mahasiswa Cuti yang semester cutinya sudah berakhir', 'belum mengajukan aktif kembali', $cutiBerakhir,
                    route('admin.pengajuan-cuti.index', ['jenis' => PengajuanAkademik::AKTIF_KEMBALI]), false));
            }
        }

        if ($user->hasPermission('admin.ujian') && Feature::aktif('ujian_susulan')) {
            $this->perTahun(PengajuanSusulan::query()->where('pengajuan_susulan.status', PengajuanSusulan::MENUNGGU)
                ->join('ujians', 'ujians.id', '=', 'pengajuan_susulan.ujian_id')
                ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'ujians.kelas_id'))
                ->each(fn (int $jumlah, int|string $taId) => $daftar->push($this->butir('Pengajuan ujian susulan', $label($taId), $jumlah,
                    route('admin.ujian-susulan.index', ['tahun_akademik_id' => $taId, 'status' => PengajuanSusulan::MENUNGGU]), true)));
        }

        if ($user->hasPermission('admin.pindah-kelas')) {
            $this->perTahun(PengajuanPindahKelas::query()->where('pengajuan_pindah_kelas.status', PengajuanPindahKelas::STATUS_PENDING)
                ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'pengajuan_pindah_kelas.kelas_asal_id'))
                ->each(fn (int $jumlah, int|string $taId) => $daftar->push($this->butir('Pengajuan pindah kelas', $label($taId), $jumlah,
                    route('admin.pindah-kelas.index', ['tahun_akademik_id' => $taId]), true)));
        }

        return $daftar->values()->all();
    }

    /**
     * Jumlah baris per tahun akademik; kueri yang tidak punya kolom tahun sendiri di-join ke kelas_kuliah dulu.
     *
     * @return Collection<int|string, int>
     */
    private function perTahun(Builder $query, string $kolom = 'kelas_kuliah.tahun_akademik_id'): Collection
    {
        return $query->selectRaw("{$kolom} as ta, count(*) as jumlah")->groupBy($kolom)->pluck('jumlah', 'ta')->map(fn ($n): int => (int) $n);
    }

    /**
     * Pengajuan akademik berstatus menunggu admin per jenis.
     *
     * @param  list<string>  $jenis
     * @return Collection<string, int>
     */
    private function perJenis(array $jenis): Collection
    {
        return PengajuanAkademik::query()->where('status', PengajuanAkademik::MENUNGGU)->whereIn('jenis', $jenis)
            ->selectRaw('jenis, count(*) as jumlah')->groupBy('jenis')->pluck('jumlah', 'jenis')->map(fn ($n): int => (int) $n);
    }

    /**
     * @return array{judul: string, keterangan: string|null, jumlah: int, tautan: string, penting: bool}
     */
    private function butir(string $judul, ?string $keterangan, int $jumlah, string $tautan, bool $penting): array
    {
        return compact('judul', 'keterangan', 'jumlah', 'tautan', 'penting');
    }

    /**
     * @return array{dijadwalkan: int, berlangsung: int, selesai: int, tautan: string}
     */
    private function perkuliahanHariIni(TahunAkademik $tahunAkademik): array
    {
        $perStatus = Pertemuan::query()
            ->whereDate('tanggal', today())
            ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $tahunAkademik->id))
            ->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return [
            'dijadwalkan' => (int) ($perStatus[Pertemuan::DIJADWALKAN] ?? 0),
            'berlangsung' => (int) ($perStatus[Pertemuan::BERLANGSUNG] ?? 0),
            'selesai' => (int) ($perStatus[Pertemuan::SELESAI] ?? 0),
            'tautan' => route('admin.presensi.index'),
        ];
    }

    /**
     * @return list<array{nama: string, jumlah: int}>
     */
    private function mahasiswaPerProdi(): array
    {
        return ProgramStudi::query()
            ->withCount(['mahasiswa' => fn (Builder $q) => $q->whereIn('status', MahasiswaProfile::STATUS_AKTIF)->whereHas('user')])
            ->orderByDesc('mahasiswa_count')->orderBy('nama_prodi')
            ->get(['id', 'jenjang', 'nama_prodi'])
            ->map(fn (ProgramStudi $prodi): array => ['nama' => trim($prodi->jenjang.' '.$prodi->nama_prodi), 'jumlah' => (int) $prodi->mahasiswa_count])
            ->all();
    }
}
