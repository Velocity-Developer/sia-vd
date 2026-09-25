<?php

namespace App;

use App\Models\DosenProfile;
use App\Models\KelasKuliah;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanIzin;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;

/**
 * Ringkasan presensi untuk Beranda: peringatan kehadiran mahasiswa dan tugas presensi dosen hari ini.
 */
class PeringatanPresensi
{
    /**
     * Mata kuliah tahun aktif yang kehadirannya di bawah batas atau jatah tidak hadirnya tinggal ≤ 1.
     *
     * @return list<array<string, mixed>>
     */
    public static function untukMahasiswa(MahasiswaProfile $mahasiswa): array
    {
        $min = PengaturanAkademik::current()->min_kehadiran_ujian;
        $kelas = KelasKuliah::query()
            ->whereHas('tahunAkademik', fn ($q) => $q->where('status', true))
            ->whereHas('krs', fn ($q) => $q->where('mahasiswa_id', $mahasiswa->id))
            ->with('mataKuliah:id,nama_matkul')
            ->withCount(['pertemuans as rencana' => fn ($q) => $q->where('jenis', Pertemuan::KULIAH)->where('status', '!=', Pertemuan::DIBATALKAN)])
            ->get(['id', 'kode_kelas', 'matkul_id']);
        $rekap = PresensiMahasiswa::rekapMahasiswa($mahasiswa->id, $kelas->pluck('id')->all());

        return $kelas->map(function (KelasKuliah $item) use ($rekap, $min): ?array {
            $r = $rekap[$item->id] ?? null;

            if ($r === null || $item->rencana === 0) {
                return null;
            }

            $sisa = (int) floor($item->rencana * (100 - $min) / 100) - ($r['dihitung'] - $r['hadir'] - $r['terlambat']);

            return ($r['persen'] < $min || $sisa <= 1) ? [
                'kelas_id' => $item->id,
                'nama_matkul' => $item->mataKuliah?->nama_matkul,
                'kode_kelas' => $item->kode_kelas,
                'persen' => $r['persen'],
                'sisa_absen' => $sisa,
            ] : null;
        })->filter()->values()->all();
    }

    /**
     * Pertemuan hari ini (termasuk sebagai pengganti), izin yang menunggu, dan mahasiswa di bawah batas kehadiran.
     *
     * @return array<string, mixed>
     */
    public static function untukDosen(DosenProfile $dosen): array
    {
        $min = PengaturanAkademik::current()->min_kehadiran_ujian;
        $kelasAktif = KelasKuliah::query()
            ->where('dosen_id', $dosen->id)
            ->whereHas('tahunAkademik', fn ($q) => $q->where('status', true))
            ->pluck('id');

        // Satu query untuk semua kelas aktif (bukan satu rekap per kelas), hanya peserta yang masih ber-KRS.
        $hadir = PresensiMahasiswa::DIHITUNG_HADIR;
        $berisiko = PresensiMahasiswa::query()
            ->pesertaAktif()
            ->join('pertemuans', 'pertemuans.id', '=', 'presensi_mahasiswas.pertemuan_id')
            ->whereIn('pertemuans.kelas_id', $kelasAktif)
            ->where('pertemuans.jenis', Pertemuan::KULIAH)
            ->where('pertemuans.status', Pertemuan::SELESAI)
            ->groupBy('pertemuans.kelas_id', 'presensi_mahasiswas.mahasiswa_id')
            ->selectRaw('pertemuans.kelas_id, presensi_mahasiswas.mahasiswa_id, COUNT(*) as total, SUM(CASE WHEN presensi_mahasiswas.status IN ('
                .implode(', ', array_fill(0, count($hadir), '?')).') THEN 1 ELSE 0 END) as hadir', $hadir)
            ->get()
            // Pembulatan sama dengan rekap kelas agar angkanya konsisten.
            ->filter(fn ($baris): bool => round($baris->hadir / $baris->total * 100, 1) < $min)
            ->count();

        return [
            'hariIni' => Pertemuan::query()
                ->whereDate('tanggal', today())
                ->where('status', '!=', Pertemuan::DIBATALKAN)
                ->where(fn ($q) => $q->whereHas('kelasKuliah', fn ($k) => $k->where('dosen_id', $dosen->id))->orWhere('dosen_id', $dosen->id))
                ->with(['kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul', 'ruang:id,kode_ruang'])
                ->orderBy('jam_mulai')
                ->get(['id', 'kelas_id', 'pertemuan_ke', 'tanggal', 'jam_mulai', 'jam_akhir', 'ruang_id', 'jenis', 'status']),
            'izinMenunggu' => PengajuanIzin::query()
                ->where('status', PengajuanIzin::MENUNGGU)
                ->whereHas('pertemuan.kelasKuliah', fn ($q) => $q->where('dosen_id', $dosen->id))
                ->count(),
            'mahasiswaBerisiko' => $berisiko,
            'minKehadiran' => $min,
        ];
    }
}
