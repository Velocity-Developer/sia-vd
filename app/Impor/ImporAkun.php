<?php

namespace App\Impor;

use App\Http\Controllers\Admin\UserController;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Impor akun beserta profilnya (mahasiswa/dosen). Akun dibuat dengan role sistem jenisnya dan langsung ditandai
 * terverifikasi (data berasal dari admin). Kata sandi dari kolom Kata Sandi; bila kosong dibuat acak dan, bila diminta,
 * pengguna dikirimi tautan atur kata sandi lewat email.
 */
abstract class ImporAkun extends Impor
{
    abstract protected function tipe(): UserType;

    /** Kolom nomor induk profil yang juga menjadi username bawaan (nim / nidn). */
    abstract protected function kolomInduk(): string;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    abstract protected function dataProfil(array $data): array;

    /**
     * Kolom akun dan biodata umum (dipakai mahasiswa dan dosen).
     *
     * @return array<string, array{0: string, 1: bool, 2: string, 3: string}>
     */
    protected function kolomAkun(): array
    {
        return [
            'nama' => ['Nama', true, 'Siti Aminah', 'Nama lengkap.'],
            'email' => ['Email', true, 'siti@contoh.ac.id', 'Unik; dipakai untuk lupa kata sandi.'],
            'username' => ['Username', false, '', 'Kosongkan untuk memakai nomor induk.'],
            'kata_sandi' => ['Kata Sandi', false, '', 'Minimal 8 karakter. Kosongkan agar dibuat acak.'],
            'jenis_kelamin' => ['Jenis Kelamin', true, 'P', 'L atau P.'],
            'tempat_lahir' => ['Tempat Lahir', true, 'Makassar', ''],
            'tanggal_lahir' => ['Tanggal Lahir', true, '2005-03-21', 'Format YYYY-MM-DD.'],
            'agama' => ['Agama', true, 'Islam', 'Lihat lembar Agama.'],
            'no_telepon' => ['No Telepon', true, '081234567890', ''],
            'alamat' => ['Alamat', true, 'Jl. Sultan Alauddin No. 1', ''],
            'kewarganegaraan' => ['Kewarganegaraan', false, 'Indonesia', 'Kosong = Indonesia.'],
        ];
    }

    protected function unikDalamBerkas(): array
    {
        return [$this->kolomInduk(), 'email', 'username'];
    }

    public function referensi(): array
    {
        return [
            'Program Studi' => ProgramStudi::query()->orderBy('nama_prodi')->get(['kode_prodi', 'nama_prodi', 'jenjang'])
                ->map(fn (ProgramStudi $p): string => "{$p->kode_prodi} = ".trim($p->jenjang.' '.$p->nama_prodi))->all(),
            'Agama' => UserController::AGAMA,
        ];
    }

    protected function normalisasi(array $data): array
    {
        $data = parent::normalisasi($data);
        $data['username'] ??= $data[$this->kolomInduk()] ?? null;
        $data['email'] = isset($data['email']) ? mb_strtolower($data['email']) : null;
        $data['jenis_kelamin'] = self::jenisKelamin($data['jenis_kelamin'] ?? null);
        $data['tanggal_lahir'] = self::tanggal($data['tanggal_lahir'] ?? null);
        $data['agama'] = self::pilihan($data['agama'] ?? null, UserController::AGAMA);
        $data['kewarganegaraan'] ??= 'Indonesia';

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function aturanAkun(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'kata_sandi' => ['nullable', 'string', 'min:8', 'max:100'],
            'jenis_kelamin' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date'],
            'agama' => ['required', Rule::in(UserController::AGAMA)],
            'no_telepon' => ['required', 'string', 'max:50'],
            'alamat' => ['required', 'string', 'max:1000'],
            'kewarganegaraan' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Id prodi dari kode prodi (hanya prodi yang terlihat oleh pengguna; akun Prodi hanya prodinya).
     */
    protected static function prodiId(?string $kode): ?int
    {
        if ($kode === null) {
            return null;
        }

        $id = ProgramStudi::query()->where('kode_prodi', $kode)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function simpan(array $data, array $opsi): void
    {
        $roleId = Role::query()->where('slug', $this->tipe()->value)->value('id');
        $sandiAcak = $data['kata_sandi'] === null;

        $user = User::query()->create([
            'name' => $data['nama'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['kata_sandi'] ?? Str::password(16),
            'role_id' => $roleId,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->setRelation('role', Role::query()->find($roleId));
        $user->profile()->create([
            'tempat_lahir' => $data['tempat_lahir'],
            'tanggal_lahir' => $data['tanggal_lahir'],
            'jenis_kelamin' => $data['jenis_kelamin'],
            'agama' => $data['agama'],
            'no_telepon' => $data['no_telepon'],
            'alamat' => $data['alamat'],
            'kewarganegaraan' => $data['kewarganegaraan'],
            ...$this->dataProfil($data),
        ]);

        if ($sandiAcak && ($opsi['kirim_tautan_sandi'] ?? false)) {
            // Dikirim sesudah transaksi tersimpan agar tidak ada surel untuk akun yang batal dibuat.
            dispatch(fn () => Password::broker()->sendResetLink(['email' => $data['email']]))->afterCommit();
        }
    }
}
