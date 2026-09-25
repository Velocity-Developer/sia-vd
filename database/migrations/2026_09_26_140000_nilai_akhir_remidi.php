<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Huruf akhir tertinggi yang boleh diberikan dosen setelah remidi. Kosong = bebas.
        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            $table->string('huruf_maks_remidi', 5)->nullable();
        });

        // Dosen menutup remidi lebih awal dari batas input nilai remidi; admin bisa membukanya lagi.
        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->timestamp('remidi_final_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->dropColumn('remidi_final_at');
        });

        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            $table->dropColumn('huruf_maks_remidi');
        });
    }
};
