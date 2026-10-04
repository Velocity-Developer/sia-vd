<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Bersama untuk menu Penilaian dan Hasil Studi: daftar mahasiswa yang punya KRS di satu tahun akademik (atau di
 * tahun mana pun), lalu KRS mahasiswa itu di tahun tersebut.
 */
trait NilaiMahasiswa
{
    use FilterKrs;

    /**
     * Props halaman daftar mahasiswa (pencarian nama/NIM, tahun akademik, prodi).
     *
     * @return array<string, mixed>
     */
    protected function daftarMahasiswaNilai(Request $request): array
    {
        [$tahun, $tahunAkademiks] = $this->tahunKrs($request);
        $tahunId = (int) $tahun?->id;
        $filter = [
            'tahun_akademik_id' => $tahun?->id,
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $mahasiswa = $this->mahasiswaKrs($tahunId, $filter['prodi_id'], $filter['search'])
            ->whereHas('krs.kelasKuliah', fn (Builder $k) => $k->where('tahun_akademik_id', $tahunId))
            ->paginate(25, ['id', 'user_id', 'nim', 'prodi_id', 'angkatan'])
            ->withQueryString();

        $mahasiswa->through(fn (MahasiswaProfile $m): array => $this->identitasMahasiswa($m));

        return [
            'mahasiswa' => $mahasiswa,
            'filter' => $filter,
            ...$this->opsiFilterKrs($tahunAkademiks),
        ];
    }

    /**
     * Props halaman daftar semua mahasiswa yang pernah ber-KRS, tanpa filter tahun akademik
     * (Pendataan Nilai Akhir, Transkrip Nilai).
     *
     * @return array<string, mixed>
     */
    protected function daftarSemuaMahasiswaNilai(Request $request): array
    {
        $filter = [
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $mahasiswa = MahasiswaProfile::query()
            ->whereHas('krs')
            ->when($filter['prodi_id'], fn (Builder $q, int $prodi) => $q->where('prodi_id', $prodi))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $m) => $m->where('nim', 'like', "%{$filter['search']}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$filter['search']}%"))))
            ->with(['user:id,name', 'prodi:id,nama_prodi,jenjang'])
            ->orderBy('nim')
            ->paginate(25, ['id', 'user_id', 'nim', 'prodi_id', 'angkatan'])
            ->withQueryString();

        $mahasiswa->through(fn (MahasiswaProfile $m): array => $this->identitasMahasiswa($m));

        return [
            'mahasiswa' => $mahasiswa,
            'filter' => $filter,
            'prodiOptions' => ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang'])
                ->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => $p->jenjang.' '.$p->nama_prodi]),
        ];
    }

    /**
     * @return array{id: int, nim: ?string, nama: ?string, prodi: ?string, angkatan: mixed}
     */
    protected function identitasMahasiswa(MahasiswaProfile $mahasiswa): array
    {
        return [
            'id' => $mahasiswa->id,
            'nim' => $mahasiswa->nim,
            'nama' => $mahasiswa->user?->name,
            'prodi' => $this->namaProdi($mahasiswa->prodi),
            'angkatan' => $mahasiswa->angkatan,
        ];
    }

    protected function tahunDipilih(Request $request): TahunAkademik
    {
        $request->validate(['tahun_akademik_id' => ['required', 'integer', 'exists:tahun_akademik,id']]);

        return TahunAkademik::query()->findOrFail($request->integer('tahun_akademik_id'));
    }

    /**
     * @return Collection<int, Krs>
     */
    protected function krsTahun(MahasiswaProfile $mahasiswa, ?TahunAkademik $tahun): Collection
    {
        return $mahasiswa->krs()
            ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', (int) $tahun?->id))
            ->with([
                'kelasKuliah:id,matkul_id,kode_kelas,tahun_akademik_id,dosen_id,nilai_final_at,nilai_dibuka_sampai',
                'kelasKuliah.mataKuliah:id,prodi_id,kode_matkul,nama_matkul,sks,tugas_akhir,jenis_penilaian',
                'kelasKuliah.tahunAkademik',
                'nilaiKomponen:id,krs_id,komponen_nilai_id,nilai',
                'validator:id,name',
            ])
            ->get()
            ->sortBy(fn (Krs $k): string => (string) $k->kelasKuliah?->mataKuliah?->kode_matkul)
            ->values();
    }
}
