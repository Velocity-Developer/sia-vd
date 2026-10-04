<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gelombang ujian komprehensif yang dibuka admin. Mahasiswa memilih gelombang saat mengajukan ujian komprehensif
 * (isian `gelombang_kompre_id` di pengajuan_akademik); ujiannya sendiri berlangsung di luar sistem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gelombang_kompre', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 150);
            $table->date('tanggal_buka');
            $table->date('tanggal_tutup');
            $table->date('tanggal_ujian');
            $table->unsignedInteger('kuota')->nullable();
            $table->string('keterangan', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gelombang_kompre');
    }
};
