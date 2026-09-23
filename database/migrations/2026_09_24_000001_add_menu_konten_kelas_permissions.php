<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Jadwal, materi, tugas, dan quiz kini punya menu sendiri, jadi izinnya ikut didaftarkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        // Katalog permission disinkronkan ulang dari kode; tidak ada yang perlu dikembalikan.
    }
};
