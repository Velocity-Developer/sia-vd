<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Izin menu Penilaian → Pendataan Nilai Akhir (admin.pendataan-nilai, bawaan Admin). Memakai kolom krs.nilai yang sudah ada.
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
