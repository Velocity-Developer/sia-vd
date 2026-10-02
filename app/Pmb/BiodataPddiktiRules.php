<?php

namespace App\Pmb;

use Illuminate\Validation\Rule;

/**
 * Isian biodata PDDIKTI mahasiswa (asal formulir PMB). Semuanya opsional di form Data Mahasiswa karena
 * mahasiswa lama belum punya datanya; kodenya sama dengan OpsiPmb.
 */
class BiodataPddiktiRules
{
    public const FIELDS = [
        'nik', 'npwp', 'status_sipil', 'telepon_wali', 'dusun', 'rt', 'rw', 'kelurahan', 'wilayah_kecamatan_id', 'kode_pos',
        'alat_transportasi', 'jenis_tinggal', 'jenis_masuk', 'penerima_kps', 'nomor_kps', 'jenis_pembiayaan', 'jumlah_pembiayaan',
        'jalur_kelas', 'nilai_un', 'asal_perguruan_tinggi', 'jenjang_asal', 'prodi_asal', 'nim_asal', 'sks_diakui',
    ];

    /** @return array<string, list<mixed>> */
    public static function rules(?int $profileId = null): array
    {
        return [
            'nik' => ['nullable', 'digits:16', Rule::unique('mahasiswa_profiles', 'nik')->ignore($profileId)],
            'npwp' => ['nullable', 'digits_between:15,16'],
            'status_sipil' => ['nullable', Rule::in(array_keys(OpsiPmb::STATUS_SIPIL))],
            'telepon_wali' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/'],
            'dusun' => ['nullable', 'string', 'max:100'],
            'rt' => ['nullable', 'digits_between:1,3'],
            'rw' => ['nullable', 'digits_between:1,3'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'wilayah_kecamatan_id' => ['nullable', 'integer', Rule::exists('wilayah_kecamatan', 'id')],
            'kode_pos' => ['nullable', 'digits:5'],
            'alat_transportasi' => ['nullable', Rule::in(array_keys(OpsiPmb::ALAT_TRANSPORTASI))],
            'jenis_tinggal' => ['nullable', Rule::in(array_keys(OpsiPmb::JENIS_TINGGAL))],
            'jenis_masuk' => ['nullable', Rule::in(array_keys(OpsiPmb::JENIS_MASUK))],
            'penerima_kps' => ['nullable', 'boolean'],
            'nomor_kps' => ['nullable', 'required_if_accepted:penerima_kps', 'string', 'max:30'],
            'jenis_pembiayaan' => ['nullable', Rule::in(array_keys(OpsiPmb::JENIS_PEMBIAYAAN))],
            'jumlah_pembiayaan' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'jalur_kelas' => ['nullable', Rule::in(array_keys(OpsiPmb::KELAS))],
            'nilai_un' => ['nullable', 'string', 'max:10'],
            'asal_perguruan_tinggi' => ['nullable', 'string', 'max:255'],
            'jenjang_asal' => ['nullable', Rule::in(array_keys(OpsiPmb::JENJANG))],
            'prodi_asal' => ['nullable', 'string', 'max:255'],
            'nim_asal' => ['nullable', 'string', 'max:30'],
            'sks_diakui' => ['nullable', 'integer', 'min:0', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'nik' => 'NIK',
            'npwp' => 'NPWP',
            'status_sipil' => 'Status Perkawinan',
            'telepon_wali' => 'Nomor HP Wali',
            'dusun' => 'Dusun',
            'rt' => 'RT',
            'rw' => 'RW',
            'kelurahan' => 'Kelurahan',
            'wilayah_kecamatan_id' => 'Kecamatan',
            'kode_pos' => 'Kode Pos',
            'alat_transportasi' => 'Alat Transportasi',
            'jenis_tinggal' => 'Jenis Tinggal',
            'jenis_masuk' => 'Jenis Masuk',
            'penerima_kps' => 'Penerima KPS',
            'nomor_kps' => 'Nomor KPS',
            'jenis_pembiayaan' => 'Jenis Pembiayaan',
            'jumlah_pembiayaan' => 'Jumlah Pembiayaan',
            'jalur_kelas' => 'Jalur Kelas',
            'nilai_un' => 'Nilai UN',
            'asal_perguruan_tinggi' => 'Asal Perguruan Tinggi',
            'jenjang_asal' => 'Jenjang Asal',
            'prodi_asal' => 'Program Studi Asal',
            'nim_asal' => 'NIM Asal',
            'sks_diakui' => 'SKS Diakui',
        ];
    }
}
