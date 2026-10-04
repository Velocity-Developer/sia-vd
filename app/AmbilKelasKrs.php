<?php

namespace App;

use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanAkademik;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Memasukkan satu kelas ke KRS mahasiswa dengan aturan yang sama untuk mahasiswa (isi KRS sendiri)
 * dan admin (Input KRS): tawaran & prasyarat, bentrok jadwal, kapasitas kelas, dan batas SKS.
 * Periode KRS dan kunci KRS dicek pemanggil, karena admin boleh mengisi di luar keduanya.
 */
class AmbilKelasKrs
{
    /**
     * Mengembalikan pesan galat, atau null bila kelas berhasil diambil.
     */
    public static function ambil(MahasiswaProfile $mahasiswa, KelasKuliah $kelasKuliah): ?string
    {
        $kelasKuliah->loadMissing('mataKuliah.prasyarat:mata_kuliahs.id,nama_matkul', 'tahunAkademik');

        // Kunci baris mahasiswa dan kelas agar dua pengambilan bersamaan tidak melewati kapasitas/SKS.
        return DB::transaction(function () use ($mahasiswa, $kelasKuliah): ?string {
            MahasiswaProfile::query()->whereKey($mahasiswa->id)->lockForUpdate()->first();
            $kelas = KelasKuliah::query()->whereKey($kelasKuliah->id)->lockForUpdate()->first();
            $tawaran = new TawaranKrs($mahasiswa, $kelasKuliah->tahunAkademik, self::semuaKrs($mahasiswa));
            $alasan = $tawaran->alasanTidakBolehAmbil($kelasKuliah->mataKuliah);

            if ($alasan !== null) {
                return $alasan;
            }

            $bentrok = Jadwal::bentrokUntukMahasiswa($kelas, $mahasiswa->id);

            if ($bentrok !== null) {
                return 'Jadwal kelas ini bentrok dengan kelas '.$bentrok->keterangan().'.';
            }

            // Kelas TA/Skripsi tidak dibatasi kapasitas: tiap mahasiswa dibimbing terpisah.
            if (! $kelasKuliah->mataKuliah->tugas_akhir && $kelas->krs()->count() >= $kelas->kapasitas) {
                return 'Kelas sudah penuh, silakan ambil kelas lain.';
            }

            $sksDiambil = self::sksDiambil($mahasiswa, $kelas->tahun_akademik_id);
            $maksSks = PengaturanAkademik::maksSksUntuk($mahasiswa->ipsSemesterSebelum($kelasKuliah->tahunAkademik)['ips'] ?? null, $mahasiswa->prodi_id);

            if ($sksDiambil + $kelasKuliah->mataKuliah->sks > $maksSks) {
                return "Total SKS melebihi batas maksimal {$maksSks} SKS (sudah diambil {$sksDiambil} SKS).";
            }

            Krs::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

            return null;
        });
    }

    public static function sksDiambil(MahasiswaProfile $mahasiswa, int $tahunAkademikId): int
    {
        return (int) $mahasiswa->krs()
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'krs.kelas_id')
            ->join('mata_kuliahs', 'mata_kuliahs.id', '=', 'kelas_kuliah.matkul_id')
            ->where('kelas_kuliah.tahun_akademik_id', $tahunAkademikId)
            ->sum('mata_kuliahs.sks');
    }

    /**
     * Seluruh KRS mahasiswa beserta data kelas yang dibutuhkan TawaranKrs.
     *
     * @return Collection<int, Krs>
     */
    public static function semuaKrs(MahasiswaProfile $mahasiswa): Collection
    {
        return $mahasiswa->krs()
            ->with([
                'kelasKuliah:id,matkul_id,tahun_akademik_id',
                'kelasKuliah.mataKuliah:id,prodi_id,sks,tugas_akhir',
                'kelasKuliah.tahunAkademik:id,tahun,semester,tanggal_mulai,status',
            ])
            ->get();
    }
}
