<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Izin menu Akademik → Hasil Studi: KHS (admin.khs) dan Transkrip Nilai (admin.transkrip-nilai), bawaan Admin.
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
