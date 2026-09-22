<?php

namespace App;

/**
 * Jenis pengguna menentukan tabel profil yang dipakai (admin/karyawan, dosen, mahasiswa).
 * Hak akses tidak lagi ditentukan di sini, melainkan oleh role & permission di database.
 */
enum UserType: string
{
    case Admin = 'admin';
    case Dosen = 'dosen';
    case Mahasiswa = 'mahasiswa';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin / Karyawan',
            self::Dosen => 'Dosen',
            self::Mahasiswa => 'Mahasiswa',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
