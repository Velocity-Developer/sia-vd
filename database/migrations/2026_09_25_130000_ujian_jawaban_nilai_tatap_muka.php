<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ujian tatap muka juga dinilai lewat tabel ini, tanpa berkas dan waktu pengumpulan.
        Schema::table('ujian_jawabans', function (Blueprint $table) {
            $table->json('berkas')->nullable()->change();
            $table->timestamp('dikumpulkan_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ujian_jawabans', function (Blueprint $table) {
            $table->json('berkas')->nullable(false)->change();
            $table->timestamp('dikumpulkan_at')->nullable(false)->change();
        });
    }
};
