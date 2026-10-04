<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Validasi nilai per KRS (Akademik → Penilaian → Detail Nilai): nilai yang sudah divalidasi terkunci untuk dosen dan
 * admin sampai validasinya dibatalkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('krs', function (Blueprint $table): void {
            $table->timestamp('nilai_divalidasi_at')->nullable()->after('nilai_angka');
            $table->foreignId('nilai_divalidasi_oleh')->nullable()->after('nilai_divalidasi_at')->constrained('users')->nullOnDelete();
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('krs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('nilai_divalidasi_oleh');
            $table->dropColumn('nilai_divalidasi_at');
        });

        PermissionCatalog::sync();
    }
};
