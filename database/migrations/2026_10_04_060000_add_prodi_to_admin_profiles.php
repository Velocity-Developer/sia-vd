<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role Prodi: akun karyawan yang terikat ke satu program studi (admin_profiles.prodi_id) dan hanya mengelola data
 * prodinya (App\LingkupProdi). Role sistem "Prodi" dibuat oleh PermissionCatalog::sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_profiles', function (Blueprint $table): void {
            $table->foreignId('prodi_id')->nullable()->after('user_id')->constrained('program_studis')->restrictOnDelete();
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('admin_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prodi_id');
        });
    }
};
