<?php

namespace App;

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\TahunAkademik;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * KRS mata kuliah KKM (Kuliah Kerja Mahasiswa: KKM/PKL/KKN). Nilai KKM disimpan di KRS seperti mata kuliah lain agar ikut KHS, IPK, dan transkrip.
 * Saat pengajuan KKM disetujui, mahasiswa dimasukkan ke kelas mata kuliah KKM prodinya di tahun akademik aktif, tanpa
 * aturan tawaran/kapasitas/batas SKS (keputusan admin).
 */
class KrsKkm
{
    /**
     * KRS KKM mahasiswa yang dipakai untuk nilai: yang terbaru (mata kuliah KKM bisa diulang).
     */
    public static function krs(int $mahasiswaId): ?Krs
    {
        return Krs::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->whereHas('kelasKuliah.mataKuliah', fn (Builder $q) => $q->where('jenis_penilaian', MataKuliah::KKM))
            ->latest('id')
            ->first();
    }

    /**
     * @return array{berhasil: bool, pesan: string}
     */
    public static function tambahkan(MahasiswaProfile $mahasiswa): array
    {
        if (self::krs($mahasiswa->id) !== null) {
            return ['berhasil' => true, 'pesan' => 'Mata kuliah KKM sudah ada di KRS mahasiswa; nilainya diisi di Penilaian → Nilai KKM.'];
        }

        $mataKuliah = MataKuliah::query()->where('jenis_penilaian', MataKuliah::KKM)->where('prodi_id', $mahasiswa->prodi_id)->orderBy('kode_matkul')->first();
        if ($mataKuliah === null) {
            return ['berhasil' => false, 'pesan' => 'Prodi mahasiswa ini belum punya mata kuliah berjenis penilaian KKM, jadi belum dimasukkan ke KRS. Tandai mata kuliahnya di Akademik → Konfigurasi → Mata Kuliah, lalu tambahkan lewat Input KRS.'];
        }

        $tahun = TahunAkademik::aktif();
        $kelas = $tahun === null ? null
            : KelasKuliah::query()->where('matkul_id', $mataKuliah->id)->where('tahun_akademik_id', $tahun->id)->orderBy('kode_kelas')->first();
        if ($kelas === null) {
            return ['berhasil' => false, 'pesan' => "Belum ada kelas {$mataKuliah->nama_matkul} di tahun akademik aktif, jadi mahasiswa belum dimasukkan ke KRS. Buat kelasnya lalu tambahkan lewat Input KRS."];
        }

        Krs::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

        return ['berhasil' => true, 'pesan' => "Mahasiswa dimasukkan ke KRS {$mataKuliah->nama_matkul} kelas {$kelas->kode_kelas}; nilainya diisi di Penilaian → Nilai KKM."];
    }
}
