<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis penilaian mata kuliah (reguler, tugas_akhir, ppl, kkm) untuk nilai TA/Skripsi, PPL, dan KKM; kolom lama
 * `tugas_akhir` tetap ada dan selalu sama dengan jenis tugas_akhir. Pembimbing 1 TA boleh kosong (tanpa fitur pendadaran
 * tidak ada pembimbing di sistem). Juga izin Nilai KKM dan pengajuan KKM/PPL/Kompre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_kuliahs', function (Blueprint $table): void {
            $table->string('jenis_penilaian', 20)->default('reguler')->after('jenis');
        });
        DB::table('mata_kuliahs')->where('tugas_akhir', true)->update(['jenis_penilaian' => 'tugas_akhir']);
        Schema::table('tugas_akhir', function (Blueprint $table): void {
            $table->unsignedBigInteger('pembimbing_1_id')->nullable()->change();
        });
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('mata_kuliahs', function (Blueprint $table): void {
            $table->dropColumn('jenis_penilaian');
        });
        // pembimbing_1_id dibiarkan nullable: TA tanpa pembimbing yang sudah ada tidak bisa dikembalikan.
        PermissionCatalog::sync();
    }
};
