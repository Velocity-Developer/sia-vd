<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Izin dosen.pengajuan-pa (bawaan Dosen): dosen PA melihat pengajuan mahasiswa bimbingan akademiknya tanpa memprosesnya.
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
