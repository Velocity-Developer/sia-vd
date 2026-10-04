<?php

namespace App\Impor;

use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use Throwable;

/**
 * Dasar impor data dari Excel (Master → Impor Data Excel). Setiap jenis data menentukan kolom template, aturan validasi
 * per baris, dan cara menyimpannya. Seluruh baris divalidasi lebih dulu; bila ada satu saja yang salah, tidak ada data
 * yang disimpan.
 */
abstract class Impor
{
    /** Batas baris per berkas agar proses tetap singkat. */
    public const MAKS_BARIS = 1000;

    /** @var array<string, class-string<self>> */
    public const JENIS = [
        'mahasiswa' => ImporMahasiswa::class,
        'dosen' => ImporDosen::class,
        'mata-kuliah' => ImporMataKuliah::class,
        'kelas-kuliah' => ImporKelasKuliah::class,
    ];

    abstract public function judul(): string;

    /** Izin yang diperlukan untuk mengimpor jenis data ini. */
    abstract public function izin(): string;

    /**
     * Kolom template: kunci => [judul kolom, wajib?, contoh isi, catatan].
     *
     * @return array<string, array{0: string, 1: bool, 2: string, 3: string}>
     */
    abstract public function kolom(): array;

    /**
     * Aturan validasi satu baris (kunci kolom template).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    abstract protected function aturan(array $data): array;

    /**
     * Simpan satu baris yang sudah lolos validasi (sudah dinormalisasi).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $opsi
     */
    abstract public function simpan(array $data, array $opsi): void;

    /**
     * Lembar referensi di template: nama lembar => daftar teks.
     *
     * @return array<string, list<string>>
     */
    public function referensi(): array
    {
        return [];
    }

    /**
     * Kolom yang nilainya harus unik di antara baris berkas (selain unik di database).
     *
     * @return list<string>
     */
    protected function unikDalamBerkas(): array
    {
        return [];
    }

    /**
     * Pesan galat tambahan per jenis data (mis. untuk kolom hasil normalisasi seperti *_id).
     *
     * @return array<string, string>
     */
    protected function pesan(): array
    {
        return [];
    }

    public static function untuk(string $jenis): self
    {
        abort_unless(isset(self::JENIS[$jenis]), 404);

        return app(self::JENIS[$jenis]);
    }

    /**
     * Ubah isian teks dari Excel ke bentuk yang dipakai aturan (mis. "L" → "Laki-laki", kode prodi → id).
     *
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    protected function normalisasi(array $data): array
    {
        return array_map(fn (string $v): ?string => $v === '' ? null : $v, $data);
    }

    /**
     * Validasi semua baris. Hasilnya data siap simpan, atau daftar galat "Baris n: pesan".
     *
     * @param  list<array{baris: int, data: array<string, string>}>  $baris
     * @return array{data: list<array<string, mixed>>, galat: list<string>}
     */
    public function periksa(array $baris): array
    {
        $kolom = $this->kolom();
        $judul = array_map(fn (array $k): string => $k[0], $kolom);
        $data = [];
        $galat = [];
        $terpakai = [];

        foreach ($baris as $b) {
            $isi = $this->normalisasi(array_intersect_key($b['data'], $kolom) + array_fill_keys(array_keys($kolom), ''));
            $validator = Validator::make($isi, $this->aturan($isi), [
                'required' => ':attribute wajib diisi.',
                'unique' => ':attribute sudah terdaftar.',
                'exists' => ':attribute tidak ditemukan.',
                'in' => ':attribute tidak valid.',
                'email' => ':attribute bukan alamat email yang valid.',
                'date' => ':attribute bukan tanggal yang valid (pakai format YYYY-MM-DD).',
                'integer' => ':attribute harus angka bulat.',
                'digits' => ':attribute harus :digits digit angka.',
                'min' => ':attribute minimal :min.',
                'max' => ':attribute maksimal :max.',
                'prodi_id.required' => 'Kode Prodi tidak ditemukan.',
                'dosen_wali_id.required' => 'NIDN Dosen Wali tidak ditemukan atau dosennya tidak aktif.',
                ...$this->pesan(),
            ], $judul);

            foreach ($this->unikDalamBerkas() as $k) {
                $nilai = mb_strtolower((string) ($isi[$k] ?? ''));
                if ($nilai === '') {
                    continue;
                }
                if (isset($terpakai[$k][$nilai])) {
                    $validator->after(fn ($v) => $v->errors()->add($k, "{$judul[$k]} sama dengan baris {$terpakai[$k][$nilai]}."));
                } else {
                    $terpakai[$k][$nilai] = $b['baris'];
                }
            }

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $pesan) {
                    $galat[] = "Baris {$b['baris']}: {$pesan}";
                }
            } else {
                $data[] = $isi;
            }
        }

        return ['data' => $data, 'galat' => $galat];
    }

    /**
     * Tanggal dari sel Excel: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, atau nomor seri tanggal Excel.
     */
    protected static function tanggal(?string $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }
        if (preg_match('/^\d{4,6}(\.\d+)?$/', $nilai) && (float) $nilai > 1000) {
            try {
                return TanggalExcel::excelToDateTimeObject((float) $nilai)->format('Y-m-d');
            } catch (Throwable) {
                return $nilai;
            }
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $nilai, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return substr($nilai, 0, 10);
    }

    protected static function jenisKelamin(?string $nilai): ?string
    {
        return match (mb_strtolower(trim((string) $nilai))) {
            'l', 'laki-laki', 'laki laki', 'pria' => 'Laki-laki',
            'p', 'perempuan', 'wanita' => 'Perempuan',
            default => $nilai,
        };
    }

    /**
     * Cocokkan teks ke salah satu pilihan tanpa beda huruf besar/kecil; teks asli dikembalikan bila tidak cocok.
     *
     * @param  list<string>  $pilihan
     */
    protected static function pilihan(?string $nilai, array $pilihan): ?string
    {
        if ($nilai === null) {
            return null;
        }
        foreach ($pilihan as $p) {
            if (mb_strtolower($p) === mb_strtolower(trim($nilai))) {
                return $p;
            }
        }

        return $nilai;
    }
}
