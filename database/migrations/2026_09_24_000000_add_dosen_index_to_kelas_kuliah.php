<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar kelas dosen memfilter dosen_id + tahun_akademik_id lalu mengurutkan kode_kelas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas_kuliah', fn (Blueprint $table) => $table->index(['dosen_id', 'tahun_akademik_id', 'kode_kelas'], 'kelas_kuliah_dosen_tahun_kode_index'));
    }

    public function down(): void
    {
        Schema::table('kelas_kuliah', fn (Blueprint $table) => $table->dropIndex('kelas_kuliah_dosen_tahun_kode_index'));
    }
};
