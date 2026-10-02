<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Izin menu Mata Kuliah Prasyarat (admin.prasyarat) untuk role Admin. Tabelnya sudah ada sejak 2026_09_28_140000.
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
