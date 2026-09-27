<?php

namespace App;

use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
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
