<?php

namespace App\Impor;

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\UserType;
use Illuminate\Validation\Rule;

class ImporMahasiswa extends ImporAkun
{
    public function judul(): string
    {
        return 'Data Mahasiswa';
    }

    public function izin(): string
    {
        return 'admin.users.mahasiswa';
    }

    protected function tipe(): UserType
    {
        return UserType::Mahasiswa;
    }

    protected function kolomInduk(): string
    {
        return 'nim';
    }

    public function kolom(): array
    {
        return [
            'nim' => ['NIM', true, '2501001', 'Unik; menjadi username bila kolom Username kosong.'],
            ...$this->kolomAkun(),
            'kode_prodi' => ['Kode Prodi', true, 'S1-KEP', 'Lihat lembar Program Studi.'],
            'angkatan' => ['Angkatan', true, '2025', 'Tahun 4 digit.'],
            'status' => ['Status', false, 'Aktif', 'Kosong = Aktif. Pilihan: '.implode(', ', MahasiswaProfile::STATUS).'.'],
            'nama_ibu_kandung' => ['Nama Ibu Kandung', true, 'Hasnah', ''],
            'nidn_dosen_wali' => ['NIDN Dosen Wali', false, '', 'NIDN dosen aktif.'],
            'nik' => ['NIK', false, '', '16 digit.'],
            'nisn' => ['NISN', false, '', '10 digit.'],
            'sekolah_asal' => ['Sekolah Asal', false, '', ''],
        ];
    }

    protected function unikDalamBerkas(): array
    {
        return [...parent::unikDalamBerkas(), 'nik', 'nisn'];
    }

    protected function normalisasi(array $data): array
    {
        $data = parent::normalisasi($data);
        $data['status'] = self::pilihan($data['status'] ?? null, MahasiswaProfile::STATUS) ?? 'Aktif';
        $data['prodi_id'] = self::prodiId($data['kode_prodi'] ?? null);
        $data['dosen_wali_id'] = isset($data['nidn_dosen_wali'])
            ? DosenProfile::query()->pilihan()->where('nidn', $data['nidn_dosen_wali'])->value('id')
            : null;

        return $data;
    }

    protected function aturan(array $data): array
    {
        return [
            ...$this->aturanAkun(),
            'nim' => ['required', 'string', 'max:50', Rule::unique('mahasiswa_profiles', 'nim')],
            'kode_prodi' => ['required', 'string'],
            'prodi_id' => [Rule::requiredIf($data['kode_prodi'] !== null)],
            'angkatan' => ['required', 'integer', 'digits:4'],
            'status' => ['required', Rule::in(MahasiswaProfile::STATUS)],
            'nama_ibu_kandung' => ['required', 'string', 'max:255'],
            'dosen_wali_id' => [Rule::requiredIf($data['nidn_dosen_wali'] !== null)],
            'nik' => ['nullable', 'digits:16', Rule::unique('mahasiswa_profiles', 'nik')],
            'nisn' => ['nullable', 'digits:10', Rule::unique('mahasiswa_profiles', 'nisn')],
            'sekolah_asal' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function dataProfil(array $data): array
    {
        return [
            'nim' => $data['nim'],
            'prodi_id' => $data['prodi_id'],
            'angkatan' => (int) $data['angkatan'],
            'status' => $data['status'],
            'nama_ibu_kandung' => $data['nama_ibu_kandung'],
            'dosen_wali_id' => $data['dosen_wali_id'],
            'nik' => $data['nik'],
            'nisn' => $data['nisn'],
            'sekolah_asal' => $data['sekolah_asal'],
        ];
    }
}
