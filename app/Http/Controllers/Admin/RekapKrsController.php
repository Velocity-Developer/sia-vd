<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FilterKrs;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap KRS satu tahun akademik: jumlah peserta per kelas, dan SKS serta status KRS per mahasiswa.
 * Bisa diunduh sebagai CSV.
 */
class RekapKrsController extends Controller
{
    use FilterKrs;

    public const TAMPILAN = ['kelas', 'mahasiswa'];

    public function index(Request $request): Response|StreamedResponse
    {
        [$tahun, $tahunAkademiks] = $this->tahunKrs($request);
        $tahunId = (int) $tahun?->id;
        $filter = [
            'tahun_akademik_id' => $tahun?->id,
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'tampilan' => in_array($request->query('tampilan'), self::TAMPILAN, true) ? $request->query('tampilan') : 'kelas',
            'search' => $request->string('search')->trim()->toString(),
        ];

        $baris = $filter['tampilan'] === 'kelas'
            ? $this->perKelas($tahunId, $filter['prodi_id'], $filter['search'])
            : $this->perMahasiswa($tahunId, $filter['prodi_id'], $filter['search']);

        if ($request->query('format') === 'csv') {
            return $this->csv($baris, $filter['tampilan'], 'rekap-krs-'.$filter['tampilan'].'-'.Str::slug($tahun?->label() ?? ''));
        }

        return Inertia::render('Admin/RekapKrs', [
            'baris' => $baris,
            'filter' => $filter,
            'ringkasan' => $this->ringkasan($tahunId, $filter['prodi_id']),
            ...$this->opsiFilterKrs($tahunAkademiks),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function perKelas(int $tahunId, ?int $prodiId, string $search): Collection
    {
        $disetujui = KrsSemester::query()->where('tahun_akademik_id', $tahunId)->where('status', KrsSemester::DISETUJUI)->select('mahasiswa_id');

        return KelasKuliah::query()
            ->where('tahun_akademik_id', $tahunId)
            ->whereHas('mataKuliah', fn (Builder $q) => $q
                ->when($prodiId, fn (Builder $q, int $prodi) => $q->where('prodi_id', $prodi))
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $m) => $m->where('nama_matkul', 'like', "%{$search}%")->orWhere('kode_matkul', 'like', "%{$search}%"))))
            ->with(['mataKuliah:id,prodi_id,kode_matkul,nama_matkul,sks,semester', 'mataKuliah.prodi:id,nama_prodi,jenjang', 'dosen:id,user_id', 'dosen.user:id,name'])
            ->withCount(['krs', 'krs as disetujui_count' => fn (Builder $q) => $q->whereIn('mahasiswa_id', $disetujui)])
            ->get()
            ->sortBy(fn (KelasKuliah $k): string => sprintf('%02d', $k->mataKuliah?->semester).$k->mataKuliah?->kode_matkul.$k->kode_kelas)
            ->values()
            ->map(fn (KelasKuliah $k): array => [
                'id' => $k->id,
                'kode_matkul' => $k->mataKuliah?->kode_matkul,
                'nama_matkul' => $k->mataKuliah?->nama_matkul,
                'prodi' => $this->namaProdi($k->mataKuliah?->prodi),
                'semester' => $k->mataKuliah?->semester,
                'sks' => (int) ($k->mataKuliah?->sks ?? 0),
                'kode_kelas' => $k->kode_kelas,
                'dosen' => $k->dosen?->user?->name,
                'kapasitas' => $k->kapasitas,
                'peserta' => $k->krs_count,
                'disetujui' => $k->disetujui_count,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function perMahasiswa(int $tahunId, ?int $prodiId, string $search): Collection
    {
        $mahasiswa = $this->mahasiswaKrs($tahunId, $prodiId, $search)
            ->with(['krsSemester' => fn ($q) => $q->where('tahun_akademik_id', $tahunId)])
            ->get(['id', 'user_id', 'nim', 'prodi_id', 'angkatan']);
        $ringkas = Krs::query()
            ->whereIn('krs.mahasiswa_id', $mahasiswa->pluck('id'))
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'krs.kelas_id')
            ->join('mata_kuliahs', 'mata_kuliahs.id', '=', 'kelas_kuliah.matkul_id')
            ->where('kelas_kuliah.tahun_akademik_id', $tahunId)
            ->groupBy('krs.mahasiswa_id')
            ->selectRaw('krs.mahasiswa_id, count(*) as mk, sum(mata_kuliahs.sks) as sks')
            ->get()
            ->keyBy('mahasiswa_id');

        return $mahasiswa->map(fn (MahasiswaProfile $m): array => [
            'id' => $m->id,
            'nim' => $m->nim,
            'nama' => $m->user?->name,
            'prodi' => $this->namaProdi($m->prodi),
            'angkatan' => $m->angkatan,
            'mk' => (int) ($ringkas[$m->id]->mk ?? 0),
            'sks' => (int) ($ringkas[$m->id]->sks ?? 0),
            'status' => $m->krsSemester->first()?->status,
        ])->values();
    }

    /**
     * Jumlah mahasiswa per status KRS (null = sudah memilih kelas tetapi belum menyimpan KRS).
     *
     * @return array<string, int>
     */
    private function ringkasan(int $tahunId, ?int $prodiId): array
    {
        $status = $this->mahasiswaKrs($tahunId, $prodiId, '')
            ->with(['krsSemester' => fn ($q) => $q->where('tahun_akademik_id', $tahunId)])
            ->get(['id'])
            ->countBy(fn (MahasiswaProfile $m): string => $m->krsSemester->first()?->status ?? 'belum_disimpan');

        return [
            'mahasiswa' => $status->sum(),
            KrsSemester::DISETUJUI => $status->get(KrsSemester::DISETUJUI, 0),
            KrsSemester::DIAJUKAN => $status->get(KrsSemester::DIAJUKAN, 0),
            KrsSemester::PERLU_REVISI => $status->get(KrsSemester::PERLU_REVISI, 0),
            'belum_disimpan' => $status->get('belum_disimpan', 0),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $baris
     */
    private function csv(Collection $baris, string $tampilan, string $nama): StreamedResponse
    {
        $label = [
            KrsSemester::DISETUJUI => 'Disetujui',
            KrsSemester::DIAJUKAN => 'Menunggu verifikasi',
            KrsSemester::PERLU_REVISI => 'Perlu revisi',
        ];

        return response()->streamDownload(function () use ($baris, $tampilan, $label): void {
            $keluar = fopen('php://output', 'w');
            fwrite($keluar, "\xEF\xBB\xBF");

            if ($tampilan === 'kelas') {
                fputcsv($keluar, ['No', 'Kode MK', 'Mata Kuliah', 'Program Studi', 'Semester', 'SKS', 'Kelas', 'Dosen', 'Kapasitas', 'Peserta KRS', 'KRS Disetujui']);
                foreach ($baris as $i => $b) {
                    fputcsv($keluar, [$i + 1, $b['kode_matkul'], $b['nama_matkul'], $b['prodi'], $b['semester'], $b['sks'], $b['kode_kelas'], $b['dosen'], $b['kapasitas'], $b['peserta'], $b['disetujui']]);
                }
            } else {
                fputcsv($keluar, ['No', 'NIM', 'Nama', 'Program Studi', 'Angkatan', 'Jumlah MK', 'Jumlah SKS', 'Status KRS']);
                foreach ($baris as $i => $b) {
                    fputcsv($keluar, [$i + 1, $b['nim'], $b['nama'], $b['prodi'], $b['angkatan'], $b['mk'], $b['sks'], $label[$b['status']] ?? 'Belum disimpan']);
                }
            }

            fclose($keluar);
        }, $nama.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
