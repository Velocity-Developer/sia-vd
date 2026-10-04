<?php

namespace App\Pmb;

use App\AllowedUpload;
use App\Http\Controllers\Admin\UserController;
use App\Models\Cmb;
use App\Models\MahasiswaProfile;
use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Salin calon maba yang diterima ke Data Mahasiswa: buat akun (username = NIM yang diisi admin, sandi acak)
 * beserta profil mahasiswanya. Dosen wali diatur kemudian lewat Set Penasehat Akademik.
 */
class SalinCalonMaba
{
    /** Nama agama di master (kode Feeder) yang berbeda dengan pilihan agama Data Mahasiswa. */
    private const AGAMA = ['Kristen' => 'Kristen Protestan', 'Katolik' => 'Kristen Katolik'];

    /**
     * Alasan calon maba belum bisa disalin, atau null bila boleh.
     */
    public static function alasanTidakBisa(Cmb $cmb): ?string
    {
        return match (true) {
            $cmb->status_pendaftaran !== Cmb::STATUS_DITERIMA => 'Hanya pendaftar berstatus Diterima yang bisa disalin ke Data Mahasiswa.',
            $cmb->mahasiswa()->exists() => 'Pendaftar ini sudah disalin ke Data Mahasiswa.',
            User::query()->where('email', $cmb->email)->exists() => "Email {$cmb->email} sudah dipakai akun lain. Ubah email akun itu dulu.",
            MahasiswaProfile::query()->where('nik', $cmb->nik)->exists() => "NIK {$cmb->nik} sudah terdaftar di Data Mahasiswa.",
            filled($cmb->nisn) && MahasiswaProfile::query()->where('nisn', $cmb->nisn)->exists() => "NISN {$cmb->nisn} sudah terdaftar di Data Mahasiswa.",
            default => null,
        };
    }

    public static function salin(Cmb $cmb, string $nim): User
    {
        $cmb->loadMissing(['periode', 'agama']);
        $berkas = [];

        try {
            // Berkas disalin (bukan dipindah) agar data pendaftar tetap utuh bila nanti dihapus terpisah.
            $disk = Storage::disk(AllowedUpload::DISK);
            foreach (['foto' => 'foto/mahasiswa', 'berkas_ijazah' => 'berkas/mahasiswa', 'berkas_transkrip' => 'berkas/mahasiswa'] as $kolom => $folder) {
                $asal = $cmb->getAttribute($kolom);
                if (filled($asal) && $disk->exists($asal)) {
                    $tujuan = $folder.'/'.Str::random(40).'.'.strtolower(pathinfo($asal, PATHINFO_EXTENSION));
                    $disk->copy($asal, $tujuan);
                    $berkas[$kolom] = $tujuan;
                }
            }

            $user = DB::transaction(function () use ($cmb, $berkas, $nim): User {
                $user = User::query()->create([
                    'name' => $cmb->nama,
                    'username' => $nim,
                    'email' => $cmb->email,
                    'password' => Str::password(32),
                    'role_id' => Role::system(UserType::Mahasiswa)->id,
                ]);
                $user->mahasiswaProfile()->create([...self::profil($cmb), 'nim' => $nim, ...$berkas]);

                return $user;
            });
        } catch (Throwable $e) {
            Storage::disk(AllowedUpload::DISK)->delete(array_values($berkas));

            throw $e;
        }

        return $user;
    }

    /**
     * Tautan atur sandi + verifikasi email ke mahasiswa baru. False bila salah satunya gagal terkirim.
     */
    public static function kirimAkses(User $user): bool
    {
        try {
            $atur = Password::broker()->sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
        } catch (Throwable $e) {
            report($e);
            $atur = false;
        }

        return $user->kirimVerifikasiEmail() && $atur;
    }

    /** @return array<string, mixed> */
    private static function profil(Cmb $cmb): array
    {
        $agama = $cmb->agama?->nama;

        return [
            'cmb_id' => $cmb->id,
            'nim' => null,
            'angkatan' => $cmb->periode->tahun_angkatan,
            'status' => $cmb->status_masuk === 'P' ? 'Pindahan' : 'Aktif',
            'prodi_id' => $cmb->program_studi_id,
            'jalur_kelas' => $cmb->kelas,
            'tempat_lahir' => $cmb->tempat_lahir,
            'tanggal_lahir' => $cmb->tanggal_lahir,
            'jenis_kelamin' => OpsiPmb::JENIS_KELAMIN[$cmb->jenis_kelamin] ?? $cmb->jenis_kelamin,
            'agama' => self::AGAMA[$agama] ?? (in_array($agama, UserController::AGAMA, true) ? $agama : 'Lainnya'),
            'nik' => $cmb->nik,
            'npwp' => $cmb->npwp,
            'status_sipil' => $cmb->status_sipil,
            'kewarganegaraan' => OpsiPmb::negara()[$cmb->kewarganegaraan] ?? $cmb->kewarganegaraan,
            'no_telepon' => $cmb->hp,
            'telepon_wali' => $cmb->telepon_wali,
            'alamat' => $cmb->jalan,
            ...$cmb->only([
                'dusun', 'rt', 'rw', 'kelurahan', 'wilayah_kecamatan_id', 'kode_pos', 'alat_transportasi', 'jenis_tinggal',
                'jenis_masuk', 'penerima_kps', 'nomor_kps', 'jenis_pembiayaan', 'jumlah_pembiayaan', 'nisn', 'nilai_un',
                'asal_perguruan_tinggi', 'jenjang_asal', 'prodi_asal', 'nim_asal', 'sks_diakui',
            ]),
            'sekolah_asal' => $cmb->asal_sekolah,
            'nama_ibu_kandung' => $cmb->nama_ibu,
        ];
    }
}
