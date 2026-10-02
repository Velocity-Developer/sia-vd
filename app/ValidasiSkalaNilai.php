<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Validasi tabel huruf nilai (huruf, bobot, angka minimal, lulus, boleh diulang). Dipakai Skala Nilai umum
 * di Pengaturan Akademik dan Bobot Nilai per prodi, agar aturan keduanya selalu sama.
 */
class ValidasiSkalaNilai
{
    /**
     * @return list<array{huruf: string, bobot: float|int|string, angka_minimal: float|int|string|null, lulus: bool, boleh_diulang: bool}>
     */
    public static function validasi(Request $request, string $kunci): array
    {
        $request->merge([
            $kunci => collect($request->input($kunci, []))
                ->map(fn ($row): array => is_array($row) ? [...$row, 'huruf' => strtoupper(trim((string) ($row['huruf'] ?? '')))] : [])
                ->all(),
        ]);

        $baris = $request->validate([
            $kunci => ['required', 'array', 'min:1'],
            "{$kunci}.*.huruf" => ['required', 'string', 'max:2', 'regex:/^[A-Z][+-]?$/', 'distinct'],
            "{$kunci}.*.bobot" => ['required', 'numeric', 'min:0', 'max:4'],
            "{$kunci}.*.angka_minimal" => ['nullable', 'numeric', 'min:0', 'max:100'],
            "{$kunci}.*.lulus" => ['required', 'boolean'],
            "{$kunci}.*.boleh_diulang" => ['required', 'boolean'],
        ], [
            'distinct' => 'Huruf nilai tidak boleh sama di dua baris.',
            'regex' => 'Huruf nilai berupa satu huruf, boleh diikuti + atau - (mis. A, B+, A-).',
            'max' => ':attribute maksimal :max.',
        ], [
            "{$kunci}.*.huruf" => 'Huruf',
            "{$kunci}.*.bobot" => 'Bobot',
            "{$kunci}.*.angka_minimal" => 'Angka minimal',
        ])[$kunci];

        // Harus ada huruf lulus dan huruf tidak lulus. Huruf tidak lulus wajib boleh diulang, kalau tidak
        // mahasiswa yang gagal tidak akan pernah bisa mengambil mata kuliah itu lagi.
        $skala = collect($baris);
        if (! $skala->contains(fn (array $row): bool => (bool) $row['lulus']) || ! $skala->contains(fn (array $row): bool => ! $row['lulus'])) {
            throw ValidationException::withMessages([
                $kunci => 'Skala nilai harus punya minimal satu huruf lulus dan satu huruf tidak lulus.',
            ]);
        }
        $tidakBisaDiulang = $skala->filter(fn (array $row): bool => ! $row['lulus'] && ! $row['boleh_diulang'])->pluck('huruf');
        if ($tidakBisaDiulang->isNotEmpty()) {
            throw ValidationException::withMessages([
                $kunci => 'Huruf tidak lulus harus boleh diulang: '.$tidakBisaDiulang->implode(', ').'.',
            ]);
        }

        // Huruf berbobot lebih tinggi harus berangka minimal lebih tinggi, agar konversi angka → huruf tidak rancu.
        $berangka = $skala->filter(fn (array $row): bool => ($row['angka_minimal'] ?? null) !== null)->sortByDesc('bobot')->values();
        foreach ($berangka as $i => $row) {
            if ($i > 0 && (float) $row['angka_minimal'] >= (float) $berangka[$i - 1]['angka_minimal']) {
                throw ValidationException::withMessages([
                    $kunci => "Angka minimal {$row['huruf']} harus lebih rendah dari angka minimal {$berangka[$i - 1]['huruf']}.",
                ]);
            }
        }

        return $baris;
    }

    /**
     * Kolom siap simpan untuk satu baris yang sudah lolos validasi.
     *
     * @param  array{huruf: string, bobot: float|int|string, angka_minimal?: float|int|string|null, lulus: bool, boleh_diulang: bool}  $row
     * @return array{huruf: string, bobot: float, angka_minimal: ?float, lulus: bool, boleh_diulang: bool}
     */
    public static function kolom(array $row): array
    {
        return [
            'huruf' => $row['huruf'],
            'bobot' => round((float) $row['bobot'], 2),
            'angka_minimal' => isset($row['angka_minimal']) ? round((float) $row['angka_minimal'], 2) : null,
            'lulus' => (bool) $row['lulus'],
            'boleh_diulang' => (bool) $row['boleh_diulang'],
        ];
    }
}
