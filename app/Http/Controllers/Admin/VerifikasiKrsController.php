<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FilterKrs;
use App\Http\Controllers\Controller;
use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\PengaturanAkademik;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Verifikasi KRS yang diajukan mahasiswa: setujui (KRS final), atau kembalikan untuk revisi dengan catatan.
 * KRS yang belum diverifikasi saat masa revisi habis tetap berstatus diajukan dan masih bisa disetujui.
 */
class VerifikasiKrsController extends Controller
{
    use FilterKrs;

    public function index(Request $request): Response
    {
        $tahunAkademiks = TahunAkademik::query()->orderByDesc('tanggal_mulai')->get(['id', 'tahun', 'semester', 'status', 'tanggal_krs_awal', 'tanggal_krs_akhir', 'tanggal_revisi_krs_akhir']);
        $tahun = $tahunAkademiks->firstWhere('id', $request->integer('tahun_akademik_id'))
            ?? $tahunAkademiks->firstWhere('status', true)
            ?? $tahunAkademiks->first();
        $filter = [
            'tahun_akademik_id' => $tahun?->id,
            'prodi_id' => $request->integer('prodi_id') ?: null,
            // Bawaan: yang menunggu verifikasi; "semua" menampilkan seluruh status.
            'status' => $request->query('status') === 'semua' ? 'semua'
                : (in_array($request->query('status'), KrsSemester::STATUS, true) ? $request->query('status') : KrsSemester::DIAJUKAN),
            'search' => $request->string('search')->trim()->toString(),
        ];

        $dasar = KrsSemester::query()
            ->where('tahun_akademik_id', (int) $tahun?->id)
            ->when($filter['prodi_id'], fn (Builder $q, int $prodi) => $q->whereHas('mahasiswa', fn (Builder $m) => $m->where('prodi_id', $prodi)));

        $krs = (clone $dasar)
            ->when($filter['status'] !== 'semua', fn (Builder $q) => $q->where('status', $filter['status']))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->whereHas('mahasiswa', fn (Builder $m) => $m->where('nim', 'like', "%{$filter['search']}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$filter['search']}%"))))
            ->with(['mahasiswa:id,user_id,nim,prodi_id,angkatan', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang', 'verifikator:id,name'])
            // Yang paling lama menunggu tampil lebih dulu.
            ->orderByRaw('status = ? desc', [KrsSemester::DIAJUKAN])
            ->orderBy('disimpan_pada')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $sks = $this->sksPerMahasiswa($krs->getCollection()->pluck('mahasiswa_id')->all(), (int) $tahun?->id);
        $krs->through(fn (KrsSemester $k): array => [
            'id' => $k->id,
            'nama' => $k->mahasiswa?->user?->name,
            'nim' => $k->mahasiswa?->nim,
            'prodi' => $k->mahasiswa?->prodi ? $k->mahasiswa->prodi->jenjang.' '.$k->mahasiswa->prodi->nama_prodi : null,
            'semester' => $tahun === null ? null : $k->mahasiswa?->semesterPada($tahun),
            'sks' => (int) ($sks[$k->mahasiswa_id] ?? 0),
            'status' => $k->status,
            'disimpan_pada' => $k->disimpan_pada?->toIso8601String(),
            'catatan_revisi' => $k->catatan_revisi,
            'dibuka_sampai' => $k->dibuka_sampai?->toDateString(),
            'diverifikasi_oleh' => $k->verifikator?->name,
            'diverifikasi_pada' => $k->diverifikasi_pada?->toIso8601String(),
        ]);

        return Inertia::render('Admin/VerifikasiKrs', [
            'krs' => $krs,
            'filter' => $filter,
            'jumlah' => (clone $dasar)->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status'),
            'tahunAkademikOptions' => $tahunAkademiks->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->label()]),
            'prodiOptions' => ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang'])
                ->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => $p->jenjang.' '.$p->nama_prodi]),
            'periode' => $tahun === null ? null : [
                'krs_awal' => $tahun->tanggal_krs_awal,
                'krs_akhir' => $tahun->tanggal_krs_akhir,
                'batas_revisi' => KrsSemester::batasRevisi($tahun)?->toDateString(),
                'masa_revisi_berjalan' => KrsSemester::masaRevisiBerjalan($tahun),
            ],
            'verifikasiAktif' => KrsSemester::verifikasiAktif(),
        ]);
    }

    /**
     * Daftar kelas satu KRS, dimuat saat admin membuka rinciannya.
     */
    public function show(KrsSemester $krsSemester): Response
    {
        $krsSemester->load(['mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang', 'tahunAkademik', 'verifikator:id,name']);
        $mahasiswa = $krsSemester->mahasiswa;
        $tahun = $krsSemester->tahunAkademik;
        $ips = $mahasiswa->ipsSemesterSebelum($tahun);

        $kelas = Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $tahun->id))
            ->with([
                'kelasKuliah:id,matkul_id,dosen_id,kode_kelas',
                'kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul,sks,semester,jenis',
                'kelasKuliah.dosen:id,user_id',
                'kelasKuliah.dosen.user:id,name',
                'kelasKuliah.jadwals' => fn ($q) => $q->with('ruang:id,kode_ruang')->orderBy('jam_mulai'),
            ])
            ->get()
            ->sortBy(fn (Krs $k): string => (string) $k->kelasKuliah?->mataKuliah?->kode_matkul)
            ->values();

        return Inertia::render('Admin/VerifikasiKrsShow', [
            'krs' => [
                'id' => $krsSemester->id,
                ...$krsSemester->ringkasan(),
                'tahun_akademik_id' => $tahun->id,
                'tahun_akademik' => $tahun->label(),
            ],
            'mahasiswa' => [
                'user_id' => $mahasiswa->user_id,
                'nama' => $mahasiswa->user?->name,
                'nim' => $mahasiswa->nim,
                'prodi' => $mahasiswa->prodi ? $mahasiswa->prodi->jenjang.' '.$mahasiswa->prodi->nama_prodi : null,
                'angkatan' => $mahasiswa->angkatan,
                'semester' => $mahasiswa->semesterPada($tahun),
                'status' => $mahasiswa->status,
            ],
            'kelas' => $kelas->map(fn (Krs $k): array => [
                'id' => $k->id,
                'kode_matkul' => $k->kelasKuliah?->mataKuliah?->kode_matkul,
                'nama_matkul' => $k->kelasKuliah?->mataKuliah?->nama_matkul,
                'semester_matkul' => $k->kelasKuliah?->mataKuliah?->semester,
                'jenis' => $k->kelasKuliah?->mataKuliah?->jenis,
                'sks' => (int) ($k->kelasKuliah?->mataKuliah?->sks ?? 0),
                'kode_kelas' => $k->kelasKuliah?->kode_kelas,
                'dosen' => $k->kelasKuliah?->dosen?->user?->name,
                'jadwal' => $k->kelasKuliah?->jadwals
                    ->map(fn ($j): string => $j->hari.', '.substr($j->jam_mulai, 0, 5).'–'.substr($j->jam_akhir, 0, 5).($j->ruang ? ' ('.$j->ruang->kode_ruang.')' : ''))
                    ->all() ?? [],
            ]),
            'maksSks' => PengaturanAkademik::maksSksUntuk($ips['ips'] ?? null, $mahasiswa->prodi_id),
            'ipsSebelumnya' => $ips === null ? null : ['ips' => $ips['ips'], 'tahun_akademik' => $ips['tahun_akademik']->label()],
            'masaRevisiBerjalan' => KrsSemester::masaRevisiBerjalan($tahun),
        ]);
    }

    public function setujui(Request $request, KrsSemester $krsSemester): RedirectResponse
    {
        if ($krsSemester->status === KrsSemester::DISETUJUI) {
            return back()->with('error', 'KRS ini sudah disetujui.');
        }

        if (! $this->punyaKelas($krsSemester)) {
            return back()->with('error', 'KRS ini belum berisi kelas, jadi belum bisa disetujui.');
        }

        $krsSemester->setujui($request->user()->id);

        return back()->with('success', 'KRS '.$krsSemester->mahasiswa?->user?->name.' disetujui.');
    }

    /**
     * Setujui sekaligus beberapa KRS berstatus diajukan.
     */
    public function setujuiMassal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', Rule::exists('krs_semester', 'id')],
        ], attributes: ['ids' => 'KRS terpilih']);

        $jumlah = DB::transaction(function () use ($data, $request): int {
            $daftar = KrsSemester::query()->whereKey($data['ids'])->where('status', KrsSemester::DIAJUKAN)->lockForUpdate()->get()
                ->filter(fn (KrsSemester $k): bool => $this->punyaKelas($k));
            $daftar->each(fn (KrsSemester $k) => $k->setujui($request->user()->id));

            return $daftar->count();
        });

        return back()->with('success', $jumlah === 0 ? 'Tidak ada KRS menunggu verifikasi yang disetujui.' : "{$jumlah} KRS disetujui.");
    }

    /**
     * Kembalikan KRS ke mahasiswa untuk direvisi. Catatan wajib karena ditampilkan ke mahasiswa.
     */
    public function revisi(Request $request, KrsSemester $krsSemester): RedirectResponse
    {
        $data = $request->validate([
            ...KrsSemester::ATURAN_BUKA_KUNCI,
            'catatan' => ['required', 'string', 'max:1000'],
        ], attributes: KrsSemester::ATRIBUT_BUKA_KUNCI);
        $tahun = $krsSemester->tahunAkademik;

        if (($galat = KrsSemester::bukaKunci($krsSemester->mahasiswa_id, $tahun, $data['catatan'], $data['dibuka_sampai'] ?? null, $request->user()->id)) !== null) {
            return back()->with('error', $galat);
        }

        return back()->with('success', 'KRS '.$krsSemester->mahasiswa?->user?->name.' dikembalikan untuk revisi. '.KrsSemester::pesanDibuka($data['dibuka_sampai'] ?? null, $tahun));
    }

    private function punyaKelas(KrsSemester $krsSemester): bool
    {
        return Krs::query()
            ->where('mahasiswa_id', $krsSemester->mahasiswa_id)
            ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $krsSemester->tahun_akademik_id))
            ->exists();
    }
}
