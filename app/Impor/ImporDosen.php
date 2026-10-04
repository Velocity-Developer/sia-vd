<?php

namespace App\Impor;

use App\Models\DosenProfile;
use App\UserType;
use Illuminate\Validation\Rule;

class ImporDosen extends ImporAkun
{
    public function judul(): string
    {
        return 'Data Dosen';
    }

    public function izin(): string
    {
        return 'admin.users.dosen';
    }

    protected function tipe(): UserType
    {
        return UserType::Dosen;
    }

    protected function kolomInduk(): string
    {
        return 'nidn';
    }

    public function kolom(): array
    {
        return [
            'nidn' => ['NIDN', true, '0912345678', 'Unik; menjadi username bila kolom Username kosong.'],
            ...$this->kolomAkun(),
            'kode_prodi' => ['Kode Prodi', false, 'S1-KEP', 'Homebase; lihat lembar Program Studi.'],
            'jabatan_fungsional' => ['Jabatan Fungsional', true, 'Lektor', ''],
            'pendidikan_terakhir' => ['Pendidikan Terakhir', true, 'S2', ''],
            'status_kepegawaian' => ['Status Kepegawaian', true, 'Dosen Tetap', ''],
            'status' => ['Status', false, 'Aktif', 'Kosong = Aktif. Pilihan: '.implode(', ', DosenProfile::STATUS).'.'],
        ];
    }

    protected function normalisasi(array $data): array
    {
        $data = parent::normalisasi($data);
        $data['status'] = self::pilihan($data['status'] ?? null, DosenProfile::STATUS) ?? 'Aktif';
        $data['prodi_id'] = self::prodiId($data['kode_prodi'] ?? null);

        return $data;
    }

    protected function aturan(array $data): array
    {
        return [
            ...$this->aturanAkun(),
            'nidn' => ['required', 'string', 'max:50', Rule::unique('dosen_profiles', 'nidn')],
            'prodi_id' => [Rule::requiredIf($data['kode_prodi'] !== null)],
            'jabatan_fungsional' => ['required', 'string', 'max:100'],
            'pendidikan_terakhir' => ['required', 'string', 'max:100'],
            'status_kepegawaian' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(DosenProfile::STATUS)],
        ];
    }

    protected function dataProfil(array $data): array
    {
        return [
            'nidn' => $data['nidn'],
            'prodi_id' => $data['prodi_id'],
            'jabatan_fungsional' => $data['jabatan_fungsional'],
            'pendidikan_terakhir' => $data['pendidikan_terakhir'],
            'status_kepegawaian' => $data['status_kepegawaian'],
            'status' => $data['status'],
        ];
    }
}
