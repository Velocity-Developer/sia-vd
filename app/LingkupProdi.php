<?php

namespace App;

use App\Models\ProgramStudi;
use App\Models\User;

/**
 * Lingkup data akun Prodi: karyawan yang profilnya terikat ke satu program studi hanya melihat dan mengubah data
 * prodinya sendiri. Pembatasan dipasang sebagai global scope di model (trait Models\Concerns\DibatasiProdi), sehingga
 * daftar, pencarian, dan route model binding otomatis tersaring; data global (Tahun Akademik, Ruang, Predikat) hanya
 * boleh dilihat (middleware BatasiAksiProdi).
 */
class LingkupProdi
{
    /** Slug role sistem Prodi. */
    public const ROLE = 'prodi';

    /** Rute yang tetap terlarang bagi akun Prodi walau izinnya dimiliki (data global lihat saja, input presensi dosen). */
    public const RUTE_TERLARANG = [
        'admin.tahun-akademik.create', 'admin.tahun-akademik.store', 'admin.tahun-akademik.edit', 'admin.tahun-akademik.update', 'admin.tahun-akademik.destroy',
        'admin.ruang.create', 'admin.ruang.store', 'admin.ruang.edit', 'admin.ruang.update', 'admin.ruang.destroy',
        'admin.predikat.create', 'admin.predikat.store', 'admin.predikat.edit', 'admin.predikat.update', 'admin.predikat.destroy',
        'admin.presensi-dosen.update',
        'admin.syarat-ujian.update-umum',
    ];

    /**
     * Id prodi yang membatasi pengguna yang sedang login, atau null bila tidak dibatasi (admin, dosen, mahasiswa, konsol).
     */
    public static function id(?User $user = null): ?int
    {
        $user ??= auth()->user();
        if (! $user instanceof User || $user->type() !== UserType::Admin) {
            return null;
        }

        $prodiId = $user->adminProfile?->prodi_id;

        return $prodiId === null ? null : (int) $prodiId;
    }

    public static function aktif(): bool
    {
        return self::id() !== null;
    }

    /**
     * @return array{id: int, nama: string}|null
     */
    public static function shared(): ?array
    {
        $id = self::id();
        $prodi = $id === null ? null : ProgramStudi::query()->find($id, ['id', 'nama_prodi', 'jenjang']);

        return $prodi === null ? null : ['id' => $prodi->id, 'nama' => trim($prodi->jenjang.' '.$prodi->nama_prodi)];
    }
}
