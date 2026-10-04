<?php

namespace App\Http\Controllers\Admin;

use App\Excel;
use App\Http\Controllers\Controller;
use App\Models\Krs;
use App\Models\PengaturanAkademik;
use App\Models\PresensiMahasiswa;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap presensi mahasiswa lintas kelas dalam satu tahun akademik: jumlah hadir/terlambat/izin/sakit/alpa dan persentase
 * kehadiran per mahasiswa per mata kuliah (pertemuan kuliah yang sudah selesai), dibandingkan syarat ujian prodinya.
 */
class RekapPresensiMahasiswaController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $this->filter($request);
        $krs = $this->kueri($filter)->paginate(25)->withQueryString();
        $baris = $this->baris($krs->getCollection());

        return Inertia::render('Admin/RekapPresensiMahasiswa', [
            'rekap' => $krs->setCollection($baris),
            'filter' => $filter,
            'tahunAkademikOptions' => TahunAkademik::query()->orderByDesc('tanggal_mulai')->get(['id', 'tahun', 'semester'])
                ->map(fn (TahunAkademik $ta): array => ['id' => $ta->id, 'name' => $ta->label()]),
            'prodiOptions' => ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang'])
                ->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => trim($p->jenjang.' '.$p->nama_prodi)]),
        ]);
    }

    public function unduh(Request $request): StreamedResponse
    {
        $filter = $this->filter($request);
        $tahun = TahunAkademik::query()->find($filter['tahun_akademik_id']);
        $judul = ['NIM', 'Nama', 'Program Studi', 'Kode MK', 'Mata Kuliah', 'Kelas', 'Pertemuan Dihitung', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', 'Kehadiran (%)', 'Syarat Ujian'];

        $isi = $this->baris($this->kueri($filter)->get())->map(fn (array $b): array => [
            $b['nim'], $b['nama'], $b['prodi'], $b['kode_matkul'], $b['nama_matkul'], $b['kode_kelas'],
            $b['dihitung'], $b['hadir'], $b['terlambat'], $b['izin'], $b['sakit'], $b['alpa'], $b['persen'],
            $b['memenuhi'] === null ? '-' : ($b['memenuhi'] ? 'Memenuhi' : 'Belum memenuhi'),
        ]);

        return Excel::unduh('rekap-presensi-mahasiswa-'.Str::slug($tahun?->label() ?? 'semua').'.xlsx', $judul, $isi, 'Rekap Presensi');
    }

    /**
     * @return array{tahun_akademik_id: ?int, prodi_id: ?int, search: string}
     */
    private function filter(Request $request): array
    {
        return [
            'tahun_akademik_id' => $request->has('tahun_akademik_id')
                ? ($request->integer('tahun_akademik_id') ?: null)
                : TahunAkademik::query()->where('status', true)->value('id'),
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'search' => $request->string('search')->trim()->toString(),
        ];
    }

    /**
     * @param  array{tahun_akademik_id: ?int, prodi_id: ?int, search: string}  $filter
     * @return Builder<Krs>
     */
    private function kueri(array $filter): Builder
    {
        $cari = $filter['search'];

        return Krs::query()
            ->select('krs.*')
            ->join('mahasiswa_profiles', 'mahasiswa_profiles.id', '=', 'krs.mahasiswa_id')
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'krs.kelas_id')
            ->when($filter['tahun_akademik_id'], fn ($q, int $id) => $q->where('kelas_kuliah.tahun_akademik_id', $id))
            ->when($filter['prodi_id'], fn ($q, int $id) => $q->where('mahasiswa_profiles.prodi_id', $id))
            ->when($cari !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('mahasiswa_profiles.nim', 'like', "%{$cari}%")
                ->orWhereHas('mahasiswa.user', fn ($u) => $u->where('name', 'like', "%{$cari}%"))))
            ->with([
                'mahasiswa:id,user_id,nim,prodi_id', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang',
                'kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul',
            ])
            ->orderBy('mahasiswa_profiles.nim')
            ->orderBy('kelas_kuliah.kode_kelas');
    }

    /**
     * @param  Collection<int, Krs>  $krs
     * @return Collection<int, array<string, mixed>>
     */
    private function baris(Collection $krs): Collection
    {
        $rekap = PresensiMahasiswa::rekapPasangan($krs->pluck('kelas_id')->unique()->values()->all(), $krs->pluck('mahasiswa_id')->unique()->values()->all());
        $syarat = $krs->pluck('mahasiswa.prodi_id')->unique()
            ->mapWithKeys(fn ($prodiId): array => [(int) $prodiId => PengaturanAkademik::untukProdi($prodiId === null ? null : (int) $prodiId)]);

        return $krs->map(function (Krs $item) use ($rekap, $syarat): array {
            $r = $rekap->get($item->kelas_id.':'.$item->mahasiswa_id) ?? ['hadir' => 0, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'dihitung' => 0, 'persen' => null];
            $pengaturan = $syarat[(int) $item->mahasiswa?->prodi_id];
            // Persentase mengikuti aturan syarat ujian prodi (izin & sakit bisa dihitung hadir).
            $hadir = $r['hadir'] + $r['terlambat'] + ($pengaturan->izin_sakit_dihitung_hadir ? $r['izin'] + $r['sakit'] : 0);
            $r['persen'] = $r['dihitung'] > 0 ? round($hadir / $r['dihitung'] * 100, 1) : null;

            return [
                'id' => $item->id,
                'nim' => $item->mahasiswa?->nim,
                'nama' => $item->mahasiswa?->user?->name,
                'prodi' => $item->mahasiswa?->prodi ? trim($item->mahasiswa->prodi->jenjang.' '.$item->mahasiswa->prodi->nama_prodi) : null,
                'kode_matkul' => $item->kelasKuliah?->mataKuliah?->kode_matkul,
                'nama_matkul' => $item->kelasKuliah?->mataKuliah?->nama_matkul,
                'kode_kelas' => $item->kelasKuliah?->kode_kelas,
                ...$r,
                // Pembanding cepat dengan syarat ujian prodi (tanpa dispensasi); null bila syarat tidak diberlakukan.
                'min' => $pengaturan->min_kehadiran_ujian,
                'memenuhi' => $pengaturan->syarat_ujian_aktif && $r['persen'] !== null ? $r['persen'] >= $pengaturan->min_kehadiran_ujian : null,
            ];
        })->values();
    }
}
