<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan khusus mata kuliah TA/Skripsi: SKS minimal sebelum boleh mengambilnya di KRS, dan kelas TA
 * boleh tanpa dosen pengampu (pembimbing ditetapkan per mahasiswa di tugas akhir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            // SKS lulus (di luar TA/Skripsi) sebelum mata kuliah TA/Skripsi boleh diambil di KRS.
            $table->unsignedSmallInteger('min_sks_ambil_ta')->default(120);
        });
        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->dropForeign(['dosen_id']);
            $table->foreignId('dosen_id')->nullable()->change();
            $table->foreign('dosen_id')->references('id')->on('dosen_profiles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->dropForeign(['dosen_id']);
            $table->foreignId('dosen_id')->nullable(false)->change();
            $table->foreign('dosen_id')->references('id')->on('dosen_profiles')->restrictOnDelete();
        });
        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            $table->dropColumn('min_sks_ambil_ta');
        });
    }
};
