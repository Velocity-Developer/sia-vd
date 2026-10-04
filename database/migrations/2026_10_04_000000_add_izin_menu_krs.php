<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Izin menu Akademik → KRS untuk role Admin: Input KRS, Status KRS, Cetak KST, Cetak Kartu Ujian, Rekap KRS.
 * Memakai tabel krs dan krs_semester yang sudah ada.
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
