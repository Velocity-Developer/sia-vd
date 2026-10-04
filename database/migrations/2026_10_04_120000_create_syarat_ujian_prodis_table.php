<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Izin/sakit dihitung hadir untuk syarat ujian (bawaan: tidak).
        Schema::table('pengaturan_akademik', function (Blueprint $table): void {
            $table->boolean('izin_sakit_dihitung_hadir')->default(false)->after('syarat_ujian_aktif');
        });

        // Syarat ujian & remedial milik prodi. Prodi tanpa baris di sini memakai pengaturan umum (pengaturan_akademik).
        Schema::create('syarat_ujian_prodis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prodi_id')->unique()->constrained('program_studis')->cascadeOnDelete();
            $table->boolean('syarat_ujian_aktif')->default(false);
            $table->unsignedTinyInteger('min_kehadiran_ujian')->default(75);
            $table->boolean('izin_sakit_dihitung_hadir')->default(false);
            $table->string('huruf_maks_remidi', 5)->nullable();
            $table->timestamps();
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('syarat_ujian_prodis');
        Schema::table('pengaturan_akademik', function (Blueprint $table): void {
            $table->dropColumn('izin_sakit_dihitung_hadir');
        });
        PermissionCatalog::sync();
    }
};
