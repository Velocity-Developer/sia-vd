<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Batas SKS per semester milik prodi: sama dengan batas_sks + maks_sks_tanpa_ips global. Prodi tanpa baris
        // di batas_sks_prodis memakai batas global.
        Schema::table('program_studis', function (Blueprint $table): void {
            $table->unsignedTinyInteger('maks_sks_tanpa_ips')->nullable()->after('sks_lulus');
        });

        Schema::create('batas_sks_prodis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prodi_id')->constrained('program_studis')->cascadeOnDelete();
            $table->decimal('ips_minimal', 3, 2);
            $table->unsignedTinyInteger('maks_sks');
            $table->timestamps();

            $table->unique(['prodi_id', 'ips_minimal']);
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('batas_sks_prodis');
        Schema::table('program_studis', function (Blueprint $table): void {
            $table->dropColumn('maks_sks_tanpa_ips');
        });
        PermissionCatalog::sync();
    }
};
