<?php

namespace App;

use App\Models\DosenProfile;
use App\Models\KelasKuliah;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;

/**
 * Kartu angka kampus di dashboard (Admin, Prodi, Dosen): jumlah mahasiswa per status, dosen, kelas, mata kuliah, prodi.
 * Untuk akun Prodi, mahasiswa/kelas/mata kuliah sudah tersaring global scope LingkupProdi; dosen disaring lewat $prodiId.
 */
class StatistikKampus
{
    public const KUNCI = ['mahasiswa', 'mahasiswa_aktif', 'mahasiswa_cuti', 'mahasiswa_lulus', 'dosen_aktif', 'kelas_kuliah', 'mata_kuliah', 'program_studi'];

    /** Kartu untuk akun Prodi (program studi selalu satu, jadi tidak ditampilkan). */
    public const KUNCI_PRODI = ['mahasiswa', 'mahasiswa_aktif', 'mahasiswa_cuti', 'mahasiswa_lulus', 'dosen_aktif', 'kelas_kuliah', 'mata_kuliah'];

    /** Kartu ringkasan kampus di beranda dosen. */
    public const KUNCI_DOSEN = ['mahasiswa', 'mahasiswa_aktif', 'dosen_aktif', 'program_studi'];

    /**
     * Hanya kunci yang diminta yang dihitung, urut sesuai KUNCI.
     *
     * @param  list<string>  $kunci
     * @return array<string, int>
     */
    public static function hitung(array $kunci, ?TahunAkademik $tahunAkademik, ?int $prodiId = null): array
    {
        $kunci = array_values(array_intersect(self::KUNCI, $kunci));
        $mahasiswa = array_intersect($kunci, ['mahasiswa', 'mahasiswa_aktif', 'mahasiswa_cuti', 'mahasiswa_lulus']) === [] ? collect()
            : MahasiswaProfile::query()->whereHas('user')->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return collect($kunci)->mapWithKeys(fn (string $k): array => [$k => match ($k) {
            'mahasiswa' => (int) $mahasiswa->sum(),
            'mahasiswa_aktif' => (int) collect(MahasiswaProfile::STATUS_AKTIF)->sum(fn (string $status): int => (int) ($mahasiswa[$status] ?? 0)),
            'mahasiswa_cuti' => (int) ($mahasiswa['Cuti'] ?? 0),
            'mahasiswa_lulus' => (int) ($mahasiswa['Lulus'] ?? 0),
            'dosen_aktif' => DosenProfile::query()->whereHas('user')->where('status', 'Aktif')
                ->when($prodiId !== null, fn ($q) => $q->where('prodi_id', $prodiId))->count(),
            'kelas_kuliah' => $tahunAkademik === null ? 0 : KelasKuliah::query()->where('tahun_akademik_id', $tahunAkademik->id)->count(),
            'mata_kuliah' => MataKuliah::query()->count(),
            'program_studi' => ProgramStudi::query()->count(),
        }])->all();
    }
}
