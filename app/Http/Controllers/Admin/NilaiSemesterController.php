<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FilterKrs;
use App\Http\Controllers\Concerns\SimpanNilaiKomponen;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\KomponenNilai;
use App\NilaiSemester;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nilai Semester: admin mengisi angka tiap komponen nilai per mahasiswa di satu kelas. Nilai akhir = rata-rata
 * berbobot persen komponen, hurufnya dari angka minimal skala nilai prodi mata kuliah.
 * Kelas PPL (dan TA/Skripsi bila pendadaran mati) diisi satu nilai akhir tanpa komponen. Kelas TA/Skripsi dengan pendadaran
 * aktif dan kelas KKM tidak lewat sini: nilainya dari hasil pendadaran / menu Nilai KKM.
 */
class NilaiSemesterController extends Controller
{
    use FilterKrs;
    use SimpanNilaiKomponen;

    public function index(Request $request): Response
    {
        [$tahun, $tahunAkademiks] = $this->tahunKrs($request);
        $filter = [
            'tahun_akademik_id' => $tahun?->id,
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'search' => $request->string('search')->trim()->toString(),
        ];
        $search = $filter['search'];

        $kelas = KelasKuliah::query()
            ->where('tahun_akademik_id', (int) $tahun?->id)
            ->whereHas('mataKuliah', fn (Builder $q) => $q
                ->when($filter['prodi_id'], fn (Builder $q, int $prodi) => $q->where('prodi_id', $prodi))
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $m) => $m->where('nama_matkul', 'like', "%{$search}%")->orWhere('kode_matkul', 'like', "%{$search}%"))))
            ->with(['mataKuliah:id,prodi_id,kode_matkul,nama_matkul,sks,semester,tugas_akhir,jenis_penilaian', 'mataKuliah.prodi:id,nama_prodi,jenjang', 'dosen:id,user_id', 'dosen.user:id,name'])
            ->withCount(['krs', 'krs as dinilai_count' => fn (Builder $q) => $q->whereNotNull('nilai')])
            ->get()
            ->each(fn (KelasKuliah $k) => $k->setRelation('tahunAkademik', $tahun))
            ->sortBy(fn (KelasKuliah $k): string => sprintf('%02d', $k->mataKuliah?->semester).$k->mataKuliah?->kode_matkul.$k->kode_kelas)
            ->values();

        $komponen = KomponenNilai::urut();

        return Inertia::render('Admin/NilaiSemester', [
            'kelas' => $kelas->map(fn (KelasKuliah $k): array => [
                'id' => $k->id,
                'kode_matkul' => $k->mataKuliah?->kode_matkul,
                'nama_matkul' => $k->mataKuliah?->nama_matkul,
                'prodi' => $this->namaProdi($k->mataKuliah?->prodi),
                'semester' => $k->mataKuliah?->semester,
                'sks' => (int) ($k->mataKuliah?->sks ?? 0),
                'kode_kelas' => $k->kode_kelas,
                'dosen' => $k->dosen?->user?->name,
                'peserta' => $k->krs_count,
                'dinilai' => $k->dinilai_count,
                'dinilai_di' => $this->dinilaiDi($k),
                'final' => $k->nilaiFinal(),
            ]),
            'komponen' => $komponen->map(fn (KomponenNilai $k): array => $k->only(['id', 'nama', 'persen'])),
            'persenLengkap' => NilaiSemester::persenLengkap($komponen),
            'filter' => $filter,
            ...$this->opsiFilterKrs($tahunAkademiks),
        ]);
    }

    public function show(KelasKuliah $kelasKuliah): Response
    {
        $kelasKuliah->load(['mataKuliah:id,prodi_id,kode_matkul,nama_matkul,sks,semester,tugas_akhir,jenis_penilaian', 'mataKuliah.prodi:id,nama_prodi,jenjang', 'dosen.user:id,name', 'tahunAkademik']);

        return Inertia::render('Admin/NilaiSemesterKelas', [
            'kelas' => [
                'id' => $kelasKuliah->id,
                'kode_matkul' => $kelasKuliah->mataKuliah?->kode_matkul,
                'nama_matkul' => $kelasKuliah->mataKuliah?->nama_matkul,
                'prodi' => $this->namaProdi($kelasKuliah->mataKuliah?->prodi),
                'sks' => (int) ($kelasKuliah->mataKuliah?->sks ?? 0),
                'kode_kelas' => $kelasKuliah->kode_kelas,
                'dosen' => $kelasKuliah->dosen?->user?->name,
                'tahun_akademik' => $kelasKuliah->tahunAkademik?->label(),
                'tahun_akademik_id' => $kelasKuliah->tahun_akademik_id,
                'dinilai_di' => $this->dinilaiDi($kelasKuliah),
                'final' => $kelasKuliah->nilaiFinal(),
            ],
            ...NilaiSemester::tabel($kelasKuliah),
        ]);
    }

    public function update(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        return $this->simpanNilaiKomponen($request, $kelasKuliah);
    }

    /**
     * Kelas yang nilainya tidak diisi di sini: 'pendadaran' (TA/Skripsi dengan pendadaran aktif) atau 'kkm'; null = di sini.
     */
    private function dinilaiDi(KelasKuliah $kelas): ?string
    {
        return match (true) {
            NilaiSemester::kkm($kelas) => 'kkm',
            $kelas->tugasAkhir() && ! NilaiSemester::langsung($kelas) => 'pendadaran',
            default => null,
        };
    }
}
