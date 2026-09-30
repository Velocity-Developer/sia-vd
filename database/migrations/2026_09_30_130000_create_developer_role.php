<?php

use App\UserType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role khusus developer untuk panel /dev (tanpa izin menu apa pun). Diberikan lewat `php artisan sia:developer`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('roles')->where('slug', 'developer')->exists()) {
            throw new RuntimeException('Sudah ada role ber-slug "developer". Ganti slug role itu dulu sebelum migrasi.');
        }

        DB::table('roles')->insert([
            'name' => 'Developer',
            'slug' => 'developer',
            'user_type' => UserType::Admin->value,
            'description' => 'Panel developer (/dev). Hanya diberikan lewat perintah sia:developer.',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::table('users')->whereIn('role_id', DB::table('roles')->where('slug', 'developer')->select('id'))->exists()) {
            throw new RuntimeException('Masih ada akun ber-role developer. Cabut dulu dengan `php artisan sia:developer <username> --cabut`.');
        }

        DB::table('roles')->where('slug', 'developer')->delete();
    }
};
