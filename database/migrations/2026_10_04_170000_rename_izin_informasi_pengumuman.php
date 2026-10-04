<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Nama izin Info Kuliah menjadi "Informasi & Pengumuman" (mengikuti nama menu di konsep Yapika).
 */
return new class extends Migration
{
    public function up(): void
    {
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        PermissionCatalog::sync();
    }
};
