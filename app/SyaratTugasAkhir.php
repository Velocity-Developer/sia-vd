<?php

namespace App;

use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\PengaturanAkademik;
use App\Models\TugasAkhir;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Daftar syarat tiap tahap tugas akhir. Form pengajuan hanya aktif bila semua syarat terpenuhi;
 * bukti bayar diunggah di form, jadi tidak termasuk syarat.
 */
class SyaratTugasAkhir
{
    /**
     * @return list<array{label: string, terpenuhi: bool, keterangan: ?string}>
     */
    public static function pengajuanTa(MahasiswaProfile $mahasiswa): array
    {
        $matkul = self::matkulTaDiambil($mahasiswa);

        return [
            [
                'label' => 'Mengambil mata kuliah TA/Skripsi di semester aktif',
                'terpenuhi' => $matkul !== null,
                'keterangan' => $matkul?->nama_matkul ?? 'Mata kuliah TA/Skripsi belum ada di KRS semester ini.',
            ],
        ];
    }

    /**
     * Syarat mendaftar pendadaran. Mata kuliah TA/Skripsi tidak ikut dihitung SKS-nya dan boleh belum dinilai.
     *
     * @return list<array{label: string, terpenuhi: bool, keterangan: ?string}>
     */
    public static function pendadaran(MahasiswaProfile $mahasiswa): array
    {
        $tugasAkhir = TugasAkhir::milik($mahasiswa->id);
        $matkul = self::matkulTaDiambil($mahasiswa);
        $minSks = PengaturanAkademik::current()->min_sks_pendadaran;

        $krs = Transkrip::krs($mahasiswa->id)->reject(fn (Krs $k): bool => (bool) $k->kelasKuliah?->mataKuliah?->tugas_akhir);
        $terbaik = Transkrip::terbaik($krs);
        $sks = $terbaik->sum(fn (Krs $k): int => $k->kelasKuliah->mataKuliah->sks);
        $nilaiE = $terbaik->filter(fn (Krs $k): bool => strtoupper((string) $k->nilai) === 'E');
        $belumDinilai = Transkrip::belumDinilai($krs);
        $nama = fn ($daftar): string => $daftar->map(fn (Krs $k): string => $k->kelasKuliah->mataKuliah->nama_matkul)->sort()->join(', ');

        return [
            [
                'label' => 'Tugas akhir sudah disahkan',
                'terpenuhi' => $tugasAkhir?->status === TugasAkhir::BERJALAN,
                'keterangan' => $tugasAkhir === null ? 'Ajukan tugas akhir terlebih dahulu.' : null,
            ],
            [
                'label' => 'Mengambil mata kuliah TA/Skripsi di semester aktif',
                'terpenuhi' => $matkul !== null,
                'keterangan' => $matkul?->nama_matkul ?? 'Mata kuliah TA/Skripsi belum ada di KRS semester ini.',
            ],
            [
                'label' => "Menempuh minimal {$minSks} SKS (di luar TA/Skripsi)",
                'terpenuhi' => $sks >= $minSks,
                'keterangan' => "Sudah {$sks} SKS bernilai.",
            ],
            [
                'label' => 'Tidak ada nilai E',
                'terpenuhi' => $nilaiE->isEmpty(),
                'keterangan' => $nilaiE->isEmpty() ? null : 'Nilai E: '.$nama($nilaiE).'.',
            ],
            [
                'label' => 'Semua mata kuliah sudah dinilai',
                'terpenuhi' => $belumDinilai->isEmpty(),
                'keterangan' => $belumDinilai->isEmpty() ? null : 'Belum dinilai: '.$nama($belumDinilai).'.',
            ],
        ];
    }

    /**
     * @param  list<array{terpenuhi: bool}>  $syarat
     */
    public static function terpenuhi(array $syarat): bool
    {
        return collect($syarat)->every(fn (array $s): bool => $s['terpenuhi']);
    }

    /**
     * Mata kuliah bertanda TA/Skripsi yang ada di KRS mahasiswa pada tahun akademik aktif.
     */
    public static function matkulTaDiambil(MahasiswaProfile $mahasiswa): ?MataKuliah
    {
        return Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereHas('kelasKuliah', fn (Builder $q) => $q
                ->whereHas('tahunAkademik', fn (Builder $t) => $t->where('status', true))
                ->whereHas('mataKuliah', fn (Builder $m) => $m->where('tugas_akhir', true)))
            ->with('kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul')
            ->first()
            ?->kelasKuliah?->mataKuliah;
    }
}
