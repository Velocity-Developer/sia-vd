<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Filter bersama menu admin KRS (Input, Verifikasi, Status, Cetak KST, Kartu Ujian, Rekap):
 * tahun akademik (bawaan: yang aktif), program studi, dan pencarian nama/NIM.
 */
trait FilterKrs
{
    /**
     * @return array{0: ?TahunAkademik, 1: Collection<int, TahunAkademik>}
     */
    protected function tahunKrs(Request $request): array
    {
        // Semua kolom dimuat: tahun terpilih dipakai juga untuk IPS & semester mahasiswa (butuh tanggal_mulai).
        $tahunAkademiks = TahunAkademik::query()->orderByDesc('tanggal_mulai')->get();
        $tahun = $tahunAkademiks->firstWhere('id', $request->integer('tahun_akademik_id'))
            ?? $tahunAkademiks->firstWhere('status', true)
            ?? $tahunAkademiks->first();

        return [$tahun, $tahunAkademiks];
    }

    /**
     * @param  Collection<int, TahunAkademik>  $tahunAkademiks
     * @return array{tahunAkademikOptions: Collection<int, array{id: int, name: string}>, prodiOptions: Collection<int, array{id: int, name: string}>}
     */
    protected function opsiFilterKrs(Collection $tahunAkademiks): array
    {
        return [
            'tahunAkademikOptions' => $tahunAkademiks->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->label().($t->status ? ' (aktif)' : '')]),
            'prodiOptions' => ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang'])
                ->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => $p->jenjang.' '.$p->nama_prodi]),
        ];
    }

    /**
     * Mahasiswa yang punya kelas di KRS tahun akademik itu, atau sudah punya status KRS (mis. dibuka admin).
     *
     * @return Builder<MahasiswaProfile>
     */
    protected function mahasiswaKrs(int $tahunAkademikId, ?int $prodiId, string $search): Builder
    {
        return MahasiswaProfile::query()
            ->where(fn (Builder $q) => $q
                ->whereHas('krs.kelasKuliah', fn (Builder $k) => $k->where('tahun_akademik_id', $tahunAkademikId))
                ->orWhereHas('krsSemester', fn (Builder $k) => $k->where('tahun_akademik_id', $tahunAkademikId)))
            ->when($prodiId, fn (Builder $q, int $prodi) => $q->where('prodi_id', $prodi))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $m) => $m->where('nim', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))))
            ->with(['user:id,name', 'prodi:id,nama_prodi,jenjang'])
            ->orderBy('nim');
    }

    /**
     * @param  Builder<MahasiswaProfile>  $query
     * @return Builder<MahasiswaProfile>
     */
    protected function hanyaKrsDisetujui(Builder $query, int $tahunAkademikId): Builder
    {
        return $query->whereHas('krsSemester', fn (Builder $k) => $k->where('tahun_akademik_id', $tahunAkademikId)->where('status', KrsSemester::DISETUJUI))
            ->whereHas('krs.kelasKuliah', fn (Builder $k) => $k->where('tahun_akademik_id', $tahunAkademikId));
    }

    protected function namaProdi(?ProgramStudi $prodi): ?string
    {
        return $prodi === null ? null : $prodi->jenjang.' '.$prodi->nama_prodi;
    }

    /**
     * @param  list<int>  $mahasiswaIds
     * @return array<int, int> mahasiswa_id => jumlah SKS diambil
     */
    protected function sksPerMahasiswa(array $mahasiswaIds, int $tahunAkademikId): array
    {
        return Krs::query()
            ->whereIn('krs.mahasiswa_id', $mahasiswaIds)
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'krs.kelas_id')
            ->join('mata_kuliahs', 'mata_kuliahs.id', '=', 'kelas_kuliah.matkul_id')
            ->where('kelas_kuliah.tahun_akademik_id', $tahunAkademikId)
            ->groupBy('krs.mahasiswa_id')
            ->selectRaw('krs.mahasiswa_id, sum(mata_kuliahs.sks) as sks')
            ->pluck('sks', 'krs.mahasiswa_id')
            ->all();
    }

    /**
     * Kelas di KRS mahasiswa pada satu tahun akademik, urut kode mata kuliah, beserta data cetak.
     *
     * @return Collection<int, Krs>
     */
    protected function kelasKrs(MahasiswaProfile $mahasiswa, int $tahunAkademikId): Collection
    {
        return $mahasiswa->krs()
            ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $tahunAkademikId))
            ->with([
                'kelasKuliah:id,matkul_id,dosen_id,kode_kelas,kapasitas,tahun_akademik_id',
                'kelasKuliah.mataKuliah:id,prodi_id,kode_matkul,nama_matkul,sks,semester,jenis',
                'kelasKuliah.dosen:id,user_id',
                'kelasKuliah.dosen.user:id,name',
                'kelasKuliah.jadwals' => fn ($q) => $q->with('ruang:id,kode_ruang')->orderBy('jam_mulai'),
            ])
            ->get()
            ->sortBy(fn (Krs $k): string => (string) $k->kelasKuliah?->mataKuliah?->kode_matkul)
            ->values();
    }
}
