<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Validasi Batas SKS per semester (tingkatan IPS minimal → maks SKS, plus maks SKS bila belum ada IPS). Dipakai
 * Batas SKS global di Pengaturan Akademik dan Batas SKS per prodi, agar aturan keduanya selalu sama.
 */
class ValidasiBatasSks
{
    /**
     * @return array{maks_sks_tanpa_ips: int, batas_sks: list<array{ips_minimal: float, maks_sks: int}>}
     */
    public static function validasi(Request $request): array
    {
        $data = $request->validate([
            'maks_sks_tanpa_ips' => ['required', 'integer', 'min:1', 'max:40'],
            'batas_sks' => ['required', 'array', 'min:1'],
            'batas_sks.*.ips_minimal' => ['required', 'numeric', 'min:0', 'max:4', 'distinct'],
            'batas_sks.*.maks_sks' => ['required', 'integer', 'min:1', 'max:40'],
        ], [
            'distinct' => 'IPS minimal tidak boleh sama di dua baris.',
            'max' => ':attribute maksimal :max.',
        ], [
            'maks_sks_tanpa_ips' => 'Maks SKS tanpa IPS',
            'batas_sks.*.ips_minimal' => 'IPS minimal',
            'batas_sks.*.maks_sks' => 'Maks SKS',
        ]);

        if (! collect($data['batas_sks'])->contains(fn (array $row): bool => (float) $row['ips_minimal'] === 0.0)) {
            throw ValidationException::withMessages([
                'batas_sks' => 'Harus ada baris dengan IPS minimal 0 agar semua IPS mendapat batas SKS.',
            ]);
        }

        return [
            'maks_sks_tanpa_ips' => (int) $data['maks_sks_tanpa_ips'],
            'batas_sks' => array_map(fn (array $row): array => ['ips_minimal' => round((float) $row['ips_minimal'], 2), 'maks_sks' => (int) $row['maks_sks']], $data['batas_sks']),
        ];
    }
}
