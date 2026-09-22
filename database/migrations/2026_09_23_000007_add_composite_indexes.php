<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk query yang sering dipakai: cek bentrok jadwal (ruang/kelas + hari), pengajuan pindah kelas
 * per mahasiswa + status, daftar mata kuliah KRS (prodi + semester), dan filter/daftar angkatan mahasiswa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwals', function (Blueprint $table): void {
            $table->index(['ruang_id', 'hari']);
            $table->index(['kelas_id', 'hari']);
        });
        Schema::table('pengajuan_pindah_kelas', fn (Blueprint $table) => $table->index(['mahasiswa_id', 'status']));
        Schema::table('mata_kuliahs', fn (Blueprint $table) => $table->index(['prodi_id', 'semester']));
        Schema::table('mahasiswa_profiles', fn (Blueprint $table) => $table->index('angkatan'));
    }

    public function down(): void
    {
        Schema::table('mahasiswa_profiles', fn (Blueprint $table) => $table->dropIndex(['angkatan']));
        Schema::table('mata_kuliahs', fn (Blueprint $table) => $table->dropIndex(['prodi_id', 'semester']));
        Schema::table('pengajuan_pindah_kelas', fn (Blueprint $table) => $table->dropIndex(['mahasiswa_id', 'status']));
        Schema::table('jadwals', function (Blueprint $table): void {
            $table->dropIndex(['kelas_id', 'hari']);
            $table->dropIndex(['ruang_id', 'hari']);
        });
    }
};
