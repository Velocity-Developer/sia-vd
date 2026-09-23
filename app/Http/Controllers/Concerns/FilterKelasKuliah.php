<?php

namespace App\Http\Controllers\Concerns;

use App\Models\DosenProfile;
use App\Models\KelasKuliah;
use App\Models\MataKuliah;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filter bersama untuk menu Jadwal Kelas, Materi, Tugas, dan Quiz: tahun akademik, program studi,
 * mata kuliah, kelas, dosen, dan pencarian teks. Dosen selalu terkunci ke kelas yang diampunya.
 */
trait FilterKelasKuliah
{
    /**
     * Nilai filter yang sedang dipakai. Tanpa pilihan tahun akademik, dipakai tahun yang sedang aktif
     * agar daftar tidak langsung menampilkan seluruh riwayat.
     *
     * @return array{tahun_akademik_id: ?int, prodi_id: ?int, mata_kuliah_id: ?int, kelas_id: ?int, dosen_id: ?int, search: string}
     */
    protected function filterKelas(Request $request): array
    {
        return [
            'tahun_akademik_id' => $request->has('tahun_akademik_id')
                ? ($request->integer('tahun_akademik_id') ?: null)
                : TahunAkademik::where('status', true)->value('id'),
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'mata_kuliah_id' => $request->integer('mata_kuliah_id') ?: null,
            'kelas_id' => $request->integer('kelas_id') ?: null,
            'dosen_id' => $this->peran() === 'dosen'
                ? $request->user()?->dosenProfile?->id
                : ($request->integer('dosen_id') ?: null),
            'search' => $request->string('search')->trim()->toString(),
        ];
    }

    /**
     * Terapkan filter kelas ke query yang punya relasi kelasKuliah.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filter
     */
    protected function terapkanFilterKelas(Builder $query, array $filter): void
    {
        $query->whereHas('kelasKuliah', fn (Builder $kelas) => $kelas
            ->when($filter['tahun_akademik_id'] !== null, fn (Builder $q) => $q->where('tahun_akademik_id', $filter['tahun_akademik_id']))
            ->when($filter['kelas_id'] !== null, fn (Builder $q) => $q->whereKey($filter['kelas_id']))
            ->when($filter['dosen_id'] !== null, fn (Builder $q) => $q->where('dosen_id', $filter['dosen_id']))
            ->when($filter['mata_kuliah_id'] !== null, fn (Builder $q) => $q->where('matkul_id', $filter['mata_kuliah_id']))
            ->when($filter['prodi_id'] !== null, fn (Builder $q) => $q->whereHas('mataKuliah', fn (Builder $matkul) => $matkul->where('prodi_id', $filter['prodi_id']))));
    }

    /**
     * Pilihan yang ditampilkan di bilah filter, mengikuti filter lain yang sedang aktif.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    protected function opsiFilterKelas(array $filter): array
    {
        $kelasTerlihat = fn (Builder $query) => $query
            ->when($filter['tahun_akademik_id'] !== null, fn (Builder $q) => $q->where('tahun_akademik_id', $filter['tahun_akademik_id']))
            ->when($filter['dosen_id'] !== null, fn (Builder $q) => $q->where('dosen_id', $filter['dosen_id']));

        return [
            'tahunAkademikOptions' => TahunAkademik::orderByDesc('tanggal_mulai')->get(['id', 'tahun', 'semester'])
                ->map(fn (TahunAkademik $tahun): array => ['id' => $tahun->id, 'name' => $tahun->tahun.' '.$tahun->semester])->all(),

            'prodiOptions' => ProgramStudi::query()
                ->whereHas('mataKuliah.kelasKuliah', $kelasTerlihat)
                ->orderBy('nama_prodi')
                ->get(['id', 'kode_prodi', 'nama_prodi'])
                ->map(fn (ProgramStudi $prodi): array => ['id' => $prodi->id, 'name' => $prodi->nama_prodi])->all(),

            'mataKuliahOptions' => MataKuliah::query()
                ->whereHas('kelasKuliah', $kelasTerlihat)
                ->when($filter['prodi_id'] !== null, fn (Builder $q) => $q->where('prodi_id', $filter['prodi_id']))
                ->orderBy('kode_matkul')
                ->get(['id', 'kode_matkul', 'nama_matkul'])
                ->map(fn (MataKuliah $matkul): array => ['id' => $matkul->id, 'name' => $matkul->kode_matkul.' — '.$matkul->nama_matkul])->all(),

            'kelasOptions' => KelasKuliah::query()
                ->where($kelasTerlihat)
                ->when($filter['mata_kuliah_id'] !== null, fn (Builder $q) => $q->where('matkul_id', $filter['mata_kuliah_id']))
                ->when($filter['prodi_id'] !== null, fn (Builder $q) => $q->whereHas('mataKuliah', fn (Builder $matkul) => $matkul->where('prodi_id', $filter['prodi_id'])))
                ->orderBy('kode_kelas')
                ->get(['id', 'kode_kelas'])
                ->map(fn (KelasKuliah $kelas): array => ['id' => $kelas->id, 'name' => $kelas->kode_kelas])->all(),

            // Dosen tidak perlu filter dosen karena daftarnya sudah terbatas pada kelasnya sendiri.
            'dosenOptions' => $this->peran() === 'admin'
                ? DosenProfile::query()
                    ->whereHas('kelasKuliah', $kelasTerlihat)
                    ->with('user:id,name')
                    ->orderBy('nidn')
                    ->get(['id', 'user_id', 'nidn'])
                    ->map(fn (DosenProfile $dosen): array => ['id' => $dosen->id, 'name' => ($dosen->user?->name ?? 'Tanpa nama').' — '.$dosen->nidn])->all()
                : [],
        ];
    }

    /**
     * Props yang selalu dikirim ke halaman daftar: nilai filter, pilihan filter, dan peran.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    protected function propsFilterKelas(array $filter): array
    {
        return [
            'peran' => $this->peran(),
            'filter' => [
                'tahun_akademik_id' => $filter['tahun_akademik_id'],
                'prodi_id' => $filter['prodi_id'],
                'mata_kuliah_id' => $filter['mata_kuliah_id'],
                'kelas_id' => $filter['kelas_id'],
                'dosen_id' => $this->peran() === 'admin' ? $filter['dosen_id'] : null,
                'search' => $filter['search'],
            ],
            ...$this->opsiFilterKelas($filter),
        ];
    }
}
