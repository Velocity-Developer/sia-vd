<?php

namespace App;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Daftar permission (menu & fitur) yang dikenal aplikasi.
 *
 * Katalog ini hanya dipakai untuk menyinkronkan tabel `permissions` dan hak akses bawaan
 * role sistem. Pengecekan akses saat runtime selalu membaca data role & permission di database.
 */
class PermissionCatalog
{
    /**
     * @return list<array{key: string, name: string, group: string, user_type: ?UserType, description: string, defaults: list<UserType>}>
     */
    public static function definitions(): array
    {
        $admin = [UserType::Admin];

        return [
            ['key' => 'admin.dashboard', 'name' => 'Dashboard Admin', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Membuka dashboard admin.', 'defaults' => $admin],
            ['key' => 'admin.info-kuliah', 'name' => 'Info Kuliah', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola pengumuman info kuliah.', 'defaults' => $admin],
            ['key' => 'admin.tahun-akademik', 'name' => 'Tahun Akademik', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola tahun akademik dan periode KRS.', 'defaults' => $admin],
            ['key' => 'admin.fakultas', 'name' => 'Fakultas', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data fakultas.', 'defaults' => $admin],
            ['key' => 'admin.program-studi', 'name' => 'Program Studi', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data program studi.', 'defaults' => $admin],
            ['key' => 'admin.mata-kuliah', 'name' => 'Mata Kuliah', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data mata kuliah.', 'defaults' => $admin],
            ['key' => 'admin.ruang', 'name' => 'Ruang', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data ruang.', 'defaults' => $admin],
            ['key' => 'admin.kelas-kuliah', 'name' => 'Kelas Kuliah', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola kelas kuliah beserta jadwal, materi, tugas, quiz, dan nilai.', 'defaults' => $admin],
            ['key' => 'admin.pindah-kelas', 'name' => 'Pindah Kelas', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Memproses pengajuan dan pengaturan pindah kelas.', 'defaults' => $admin],
            ['key' => 'admin.users.dosen', 'name' => 'Manage User Dosen', 'group' => 'Manajemen Pengguna', 'user_type' => null, 'description' => 'Mengelola akun dan profil dosen.', 'defaults' => $admin],
            ['key' => 'admin.users.mahasiswa', 'name' => 'Manage User Mahasiswa', 'group' => 'Manajemen Pengguna', 'user_type' => null, 'description' => 'Mengelola akun dan profil mahasiswa.', 'defaults' => $admin],
            ['key' => 'admin.users.karyawan', 'name' => 'Manage User Karyawan', 'group' => 'Manajemen Pengguna', 'user_type' => null, 'description' => 'Mengelola akun dan profil karyawan.', 'defaults' => $admin],
            ['key' => 'admin.roles', 'name' => 'Kelola Role', 'group' => 'Manajemen Pengguna', 'user_type' => null, 'description' => 'Menambah, mengubah, menghapus role dan mengatur hak aksesnya.', 'defaults' => $admin],
            ['key' => 'admin.institusi', 'name' => 'Pengaturan Institusi', 'group' => 'Manajemen Pengguna', 'user_type' => null, 'description' => 'Mengubah identitas dan logo institusi.', 'defaults' => $admin],

            ['key' => 'dosen.dashboard', 'name' => 'Beranda Dosen', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Membuka beranda dan profil dosen.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.kelas-kuliah', 'name' => 'Kelas Kuliah Dosen', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Mengelola kelas yang diampu beserta materi, tugas, quiz, dan nilai.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.mahasiswa-kelas', 'name' => 'Mahasiswa Kelas', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Melihat daftar mahasiswa di kelas yang diampu.', 'defaults' => [UserType::Dosen]],

            ['key' => 'mahasiswa.dashboard', 'name' => 'Beranda Mahasiswa', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Membuka beranda, profil, dan halaman informasi mahasiswa.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.info-kuliah', 'name' => 'Info Kuliah Mahasiswa', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Melihat pengumuman info kuliah.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.krs', 'name' => 'Rencana Studi (KRS)', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Mengisi kartu rencana studi.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.hasil-studi', 'name' => 'Hasil Studi & Transkrip', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Melihat dan mengunduh KHS serta transkrip nilai.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.jadwal-kuliah', 'name' => 'Jadwal & Kelas Kuliah', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Melihat jadwal, materi, serta mengerjakan tugas dan quiz.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.pindah-kelas', 'name' => 'Pengajuan Pindah Kelas', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Mengajukan pindah kelas.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.perpustakaan', 'name' => 'Perpustakaan', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Membuka menu perpustakaan dan riwayat pinjaman.', 'defaults' => [UserType::Mahasiswa]],
        ];
    }

    /**
     * Sinkronkan tabel permissions dan role sistem.
     *
     * Role sistem baru mendapat seluruh hak akses bawaannya. Untuk role sistem yang sudah ada,
     * hanya permission yang baru ditambahkan ke katalog yang diberikan, sehingga pengaturan
     * yang sudah diubah Admin tidak ditimpa.
     */
    public static function sync(): void
    {
        DB::transaction(function (): void {
            $definitions = self::definitions();
            $newKeys = [];

            foreach ($definitions as $order => $definition) {
                $permission = Permission::query()->updateOrCreate(['key' => $definition['key']], [
                    'name' => $definition['name'],
                    'group' => $definition['group'],
                    'user_type' => $definition['user_type'],
                    'description' => $definition['description'],
                    'sort_order' => $order + 1,
                ]);

                if ($permission->wasRecentlyCreated) {
                    $newKeys[] = $definition['key'];
                }
            }

            foreach (UserType::cases() as $type) {
                $role = Role::query()->firstOrCreate(['slug' => $type->value], [
                    'name' => Str::headline($type->value),
                    'user_type' => $type,
                    'description' => 'Role bawaan untuk '.$type->label().'.',
                    'is_system' => true,
                ]);

                $defaultKeys = collect($definitions)
                    ->filter(fn (array $definition): bool => in_array($type, $definition['defaults'], true))
                    ->pluck('key')
                    ->when(! $role->wasRecentlyCreated, fn ($keys) => $keys->intersect($newKeys));

                if ($defaultKeys->isNotEmpty()) {
                    $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('key', $defaultKeys)->pluck('id'));
                }
            }
        });
    }
}
