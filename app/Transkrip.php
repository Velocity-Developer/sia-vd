<?php

namespace App;

use App\Models\Krs;
use Illuminate\Support\Collection;

/**
 * Transkrip mahasiswa: mata kuliah yang diulang dihitung sekali, memakai nilai terbaiknya.
 */
class Transkrip
{
    /**
     * Seluruh KRS mahasiswa beserta mata kuliahnya (dinilai maupun belum).
     *
     * @return Collection<int, Krs>
     */
    public static function krs(int $mahasiswaId): Collection
    {
        return Krs::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->with(['kelasKuliah:id,matkul_id', 'kelasKuliah.mataKuliah:id,prodi_id,kode_matkul,nama_matkul,jenis,sks,tugas_akhir'])
            ->get(['id', 'kelas_id', 'nilai', 'nilai_divalidasi_at']);
    }

    /**
     * KRS bernilai terbaik per mata kuliah, dikunci dengan id mata kuliah.
     *
     * @param  Collection<int, Krs>  $krs
     * @return Collection<int, Krs>
     */
    public static function terbaik(Collection $krs): Collection
    {
        return $krs->filter(fn (Krs $item): bool => $item->bobotNilai() !== null && ($item->kelasKuliah?->mataKuliah?->sks ?? 0) > 0)
            ->groupBy(fn (Krs $item): int => $item->kelasKuliah->matkul_id)
            ->map(fn (Collection $percobaan): Krs => $percobaan->sortByDesc(fn (Krs $item): float => $item->bobotNilai())->first());
    }

    /**
     * Mata kuliah yang diambil tetapi belum punya nilai sama sekali (semua percobaannya belum dinilai).
     *
     * @param  Collection<int, Krs>  $krs
     * @return Collection<int, Krs>
     */
    public static function belumDinilai(Collection $krs): Collection
    {
        $dinilai = self::terbaik($krs)->keys()->flip();

        return $krs->filter(fn (Krs $item): bool => $item->kelasKuliah?->mataKuliah !== null && ! $dinilai->has($item->kelasKuliah->matkul_id))
            ->unique(fn (Krs $item): int => $item->kelasKuliah->matkul_id)
            ->values();
    }

    /**
     * IPK dan jumlah SKS dari nilai terbaik per mata kuliah (rumus sama dengan halaman Transkrip Nilai).
     *
     * @return array{ipk: float|null, sks: int, sks_lulus: int}
     */
    public static function ringkasan(int $mahasiswaId): array
    {
        $terbaik = self::terbaik(self::krs($mahasiswaId)->whereNotNull('nilai'));
        $sks = (int) $terbaik->sum(fn (Krs $item): int => $item->kelasKuliah->mataKuliah->sks);
        $mutu = $terbaik->sum(fn (Krs $item): float => $item->kelasKuliah->mataKuliah->sks * $item->bobotNilai());

        return [
            'ipk' => $sks > 0 ? round($mutu / $sks, 2) : null,
            'sks' => $sks,
            'sks_lulus' => (int) $terbaik->filter(fn (Krs $item): bool => $item->nilaiLulus())->sum(fn (Krs $item): int => $item->kelasKuliah->mataKuliah->sks),
        ];
    }
}
