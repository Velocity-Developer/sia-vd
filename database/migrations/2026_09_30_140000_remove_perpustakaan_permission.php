<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Perpustakaan (placeholder) dihapus; izinnya ikut dibuang dari semua role.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Baris permission_role ikut terhapus (cascadeOnDelete).
        DB::table('permissions')->where('key', 'mahasiswa.perpustakaan')->delete();
    }

    public function down(): void
    {
        // Menu dan izinnya sudah tidak ada di kode, jadi tidak dipulihkan.
    }
};
