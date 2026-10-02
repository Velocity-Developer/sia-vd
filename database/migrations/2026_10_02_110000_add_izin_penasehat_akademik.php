<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Izin menu Set Penasehat Akademik (admin.penasehat-akademik) untuk role Admin. Memakai kolom dosen_wali_id yang sudah ada.
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
