<?php

namespace App;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pembuat dan pembaca berkas Excel (.xlsx) sederhana: satu lembar dengan baris judul kolom, dipakai rekap dan impor data.
 */
class Excel
{
    /**
     * Unduhan .xlsx dari judul kolom dan baris data. Semua sel ditulis sebagai teks kecuali angka, agar NIM/NIK/nomor HP
     * tidak berubah menjadi notasi ilmiah.
     *
     * @param  list<string>  $judul
     * @param  iterable<int, list<mixed>>  $baris
     * @param  array<string, list<string>>  $lembarTambahan  nama lembar => isi satu kolom (mis. daftar kode pilihan)
     * @param  bool  $template  sel isian (baris 2–1001) diformat teks
     */
    public static function unduh(string $namaBerkas, array $judul, iterable $baris, string $namaLembar = 'Data', array $lembarTambahan = [], bool $template = false): StreamedResponse
    {
        $buku = new Spreadsheet;
        $lembar = $buku->getActiveSheet()->setTitle(mb_substr($namaLembar, 0, 31));
        self::isiLembar($lembar, $judul, $baris);
        if ($template) {
            // Sel isian berformat teks agar angka seperti NIDN/NIK/nomor HP tidak kehilangan nol di depan.
            $lembar->getStyle([1, 2, max(1, count($judul)), 1001])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        foreach ($lembarTambahan as $nama => $isi) {
            $lembar = $buku->createSheet()->setTitle(mb_substr($nama, 0, 31));
            foreach (array_values($isi) as $i => $teks) {
                $lembar->setCellValueExplicit([1, $i + 1], $teks, DataType::TYPE_STRING);
            }
            $lembar->getColumnDimension('A')->setAutoSize(true);
        }
        $buku->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($buku): void {
            IOFactory::createWriter($buku, 'Xlsx')->save('php://output');
            $buku->disconnectWorksheets();
        }, $namaBerkas, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Baca lembar pertama: baris pertama = judul kolom (dicocokkan tanpa beda huruf besar/kecil dan spasi), baris
     * kosong dilewati. Setiap baris dikembalikan sebagai [judul => teks] beserta nomor barisnya di Excel.
     *
     * @return list<array{baris: int, data: array<string, string>}>
     */
    public static function baca(string $path): array
    {
        $buku = IOFactory::load($path);
        $isi = $buku->getSheet(0)->toArray(null, true, false, false);
        $buku->disconnectWorksheets();

        $judul = array_map(fn ($teks): string => self::kunci((string) $teks), array_shift($isi) ?? []);
        $hasil = [];
        foreach ($isi as $i => $sel) {
            $data = [];
            foreach ($judul as $kolom => $kunci) {
                if ($kunci !== '') {
                    $nilai = $sel[$kolom] ?? null;
                    $data[$kunci] = is_float($nilai) && floor($nilai) === $nilai ? (string) (int) $nilai : trim((string) $nilai);
                }
            }
            if (array_filter($data, fn (string $v): bool => $v !== '') !== []) {
                $hasil[] = ['baris' => $i + 2, 'data' => $data];
            }
        }

        return $hasil;
    }

    /**
     * Bentuk kunci judul kolom: huruf kecil, spasi/tanda baca jadi garis bawah ("Tanggal Lahir" → tanggal_lahir).
     */
    public static function kunci(string $judul): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim(preg_replace('/\s*\(.*?\)\s*/', ' ', $judul) ?? ''))), '_');
    }

    /**
     * @param  list<string>  $judul
     * @param  iterable<int, list<mixed>>  $baris
     */
    private static function isiLembar(Worksheet $lembar, array $judul, iterable $baris): void
    {
        foreach ($judul as $i => $teks) {
            $lembar->setCellValueExplicit([$i + 1, 1], $teks, DataType::TYPE_STRING);
        }
        $lembar->getStyle([1, 1, max(1, count($judul)), 1])->getFont()->setBold(true);
        $lembar->freezePane('A2');

        $nomor = 2;
        foreach ($baris as $isi) {
            foreach (array_values($isi) as $i => $nilai) {
                if (is_int($nilai) || is_float($nilai)) {
                    $lembar->setCellValue([$i + 1, $nomor], $nilai);
                } elseif ($nilai !== null && $nilai !== '') {
                    $lembar->setCellValueExplicit([$i + 1, $nomor], (string) $nilai, DataType::TYPE_STRING);
                }
            }
            $nomor++;
        }

        foreach (range(1, max(1, count($judul))) as $kolom) {
            $lembar->getColumnDimension(Coordinate::stringFromColumnIndex($kolom))->setAutoSize(true);
        }
    }
}
