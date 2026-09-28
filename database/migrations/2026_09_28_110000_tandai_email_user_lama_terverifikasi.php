<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi email mulai diwajibkan. Akun yang sudah ada dianggap terverifikasi agar tidak
 * terkunci; verifikasi berlaku untuk akun baru dan saat alamat email diganti.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Tidak dapat membedakan akun yang ditandai oleh migrasi ini.
    }
};
