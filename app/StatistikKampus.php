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

    /**
     * Data grafik dashboard: komposisi status mahasiswa (donat) dan sebaran mahasiswa aktif (batang). Akun Prodi
     * ($prodiId terisi) mendapat sebaran per angkatan karena prodinya hanya satu; selain itu per program studi.
     *
     * @return array{status: list<array{nama: string, jumlah: int}>, sebaran: array{judul: string, data: list<array{nama: string, jumlah: int}>}}
     */
    public static function grafik(?int $prodiId = null): array
    {
        $perStatus = MahasiswaProfile::query()->whereHas('user')->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $status = collect(MahasiswaProfile::STATUS)
            ->map(fn (string $s): array => ['nama' => $s, 'jumlah' => (int) ($perStatus[$s] ?? 0)])
            ->filter(fn (array $b): bool => $b['jumlah'] > 0)->values()->all();

        $sebaran = $prodiId !== null
            ? ['judul' => 'Mahasiswa aktif per angkatan', 'data' => MahasiswaProfile::query()->aktif()->whereHas('user')->whereNotNull('angkatan')
                ->selectRaw('angkatan, count(*) as jumlah')->groupBy('angkatan')->orderByDesc('angkatan')->limit(8)->get()
                ->map(fn ($b): array => ['nama' => 'Angkatan '.$b->angkatan, 'jumlah' => (int) $b->jumlah])->all()]
            : ['judul' => 'Mahasiswa aktif per program studi', 'data' => ProgramStudi::query()
                ->withCount(['mahasiswa' => fn ($q) => $q->whereIn('status', MahasiswaProfile::STATUS_AKTIF)->whereHas('user')])
                ->orderByDesc('mahasiswa_count')->orderBy('nama_prodi')->get(['id', 'jenjang', 'nama_prodi'])
                ->map(fn (ProgramStudi $p): array => ['nama' => trim($p->jenjang.' '.$p->nama_prodi), 'jumlah' => (int) $p->mahasiswa_count])->all()];

        return ['status' => $status, 'sebaran' => $sebaran];
    }
}
