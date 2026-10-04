<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ketua kelas = satu mahasiswa peserta kelas kuliah (Akademik → Perkuliahan → Ketua Kelas).
        Schema::table('kelas_kuliah', function (Blueprint $table): void {
            $table->foreignId('ketua_kelas_id')->nullable()->after('dosen_id')->constrained('mahasiswa_profiles')->nullOnDelete();
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('kelas_kuliah', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ketua_kelas_id');
        });
        PermissionCatalog::sync();
    }
};
