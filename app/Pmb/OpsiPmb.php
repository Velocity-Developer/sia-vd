<?php

namespace App\Pmb;

/**
 * Pilihan isian formulir PMB; kode mengikuti pmb.stikesyapika.ac.id (kode Feeder PDDIKTI).
 */
class OpsiPmb
{
    public const JENIS_KELAMIN = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    public const STATUS_SIPIL = ['B' => 'Bujangan', 'K' => 'Menikah', 'D' => 'Duda', 'J' => 'Janda', 'L' => 'Lain-lain'];

    public const ALAT_TRANSPORTASI = [
        1 => 'Jalan kaki', 2 => 'Kendaraan pribadi', 3 => 'Angkutan umum/bus/pete-pete', 4 => 'Mobil/bus antar jemput',
        5 => 'Kereta api', 6 => 'Ojek', 7 => 'Andong/bendi/sado/dokar/delman/becak', 8 => 'Perahu penyeberangan/rakit/getek',
        11 => 'Kuda', 12 => 'Sepeda', 13 => 'Sepeda motor', 14 => 'Mobil pribadi', 99 => 'Lainnya',
    ];

    public const JENIS_TINGGAL = [1 => 'Bersama orang tua', 2 => 'Wali', 3 => 'Kost', 4 => 'Asrama', 5 => 'Panti asuhan', 99 => 'Lainnya'];

    public const JENIS_MASUK = [
        3 => 'Penelusuran Minat dan Kemampuan (PMDK)', 4 => 'Prestasi', 9 => 'Program Internasional',
        11 => 'Program Kerjasama Perusahaan/Institusi/Pemerintah', 12 => 'Seleksi Mandiri', 13 => 'Ujian Masuk Bersama Lainnya',
        14 => 'Seleksi Nasional Berdasarkan Tes (SNBT)',
    ];

    public const JENIS_PEMBIAYAAN = [1 => 'Mandiri', 2 => 'Beasiswa Tidak Penuh', 3 => 'Beasiswa Penuh'];

    public const KELAS = ['R' => 'Reguler', 'K' => 'RPL'];

    public const STATUS_MASUK = ['B' => 'Peserta didik baru', 'P' => 'Pindahan'];

    public const JENJANG = ['A' => 'S3', 'B' => 'S2', 'C' => 'S1', 'D' => 'D4', 'E' => 'D3', 'F' => 'D2', 'G' => 'D1'];

    public const STATUS_PENDAFTARAN = ['diterima' => 'Diterima', 'ditolak' => 'Ditolak'];

    /** @return array<string, string> kode → nama negara */
    public static function negara(): array
    {
        return once(fn (): array => json_decode(file_get_contents(database_path('data/negara.json')), true));
    }

    /**
     * Pilihan untuk form (urutan dipertahankan sebagai daftar karena kunci angka diurutkan ulang oleh JSON).
     *
     * @return array<string, list<array{value: string|int, label: string}>>
     */
    public static function untukForm(): array
    {
        $daftar = fn (array $opsi): array => collect($opsi)->map(fn (string $label, string|int $value): array => ['value' => $value, 'label' => $label])->values()->all();

        return [
            'jenis_kelamin' => $daftar(self::JENIS_KELAMIN),
            'status_sipil' => $daftar(self::STATUS_SIPIL),
            'kewarganegaraan' => $daftar(self::negara()),
            'alat_transportasi' => $daftar(self::ALAT_TRANSPORTASI),
            'jenis_tinggal' => $daftar(self::JENIS_TINGGAL),
            'jenis_masuk' => $daftar(self::JENIS_MASUK),
            'jenis_pembiayaan' => $daftar(self::JENIS_PEMBIAYAAN),
            'kelas' => $daftar(self::KELAS),
            'status_masuk' => $daftar(self::STATUS_MASUK),
            'jenjang' => $daftar(self::JENJANG),
        ];
    }
}
