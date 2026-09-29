<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tahun_akademik', function (Blueprint $table) {
            // Periode mahasiswa boleh mengajukan cuti UNTUK semester ini. Kosong berarti tidak dibuka.
            $table->date('tanggal_cuti_awal')->nullable()->after('tanggal_krs_akhir');
            $table->date('tanggal_cuti_akhir')->nullable()->after('tanggal_cuti_awal');
        });
        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            // Jumlah semester cuti yang disetujui selama studi.
            $table->unsignedTinyInteger('maks_cuti')->default(2);
        });
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            $table->dropColumn('maks_cuti');
        });
        Schema::table('tahun_akademik', function (Blueprint $table) {
            $table->dropColumn(['tanggal_cuti_awal', 'tanggal_cuti_akhir']);
        });
    }
};
