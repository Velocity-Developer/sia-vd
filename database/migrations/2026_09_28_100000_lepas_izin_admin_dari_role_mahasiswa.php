<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu admin (permission tanpa jenis pengguna) kini terlarang untuk role Mahasiswa.
 * Sambungan lama yang mungkin sudah tercentang dilepas agar data sesuai aturan baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permission_role')
            ->whereIn('role_id', DB::table('roles')->where('user_type', 'mahasiswa')->select('id'))
            ->whereIn('permission_id', DB::table('permissions')->whereNull('user_type')->select('id'))
            ->delete();
    }

    public function down(): void
    {
        // Sambungan yang dilepas tidak dikembalikan.
    }
};
