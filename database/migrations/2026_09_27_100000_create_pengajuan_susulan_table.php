<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            // Pengajuan susulan dibuka sejak jadwal ujian terbit sampai N hari sesudah ujian.
            $table->unsignedTinyInteger('batas_pengajuan_susulan_hari')->default(3);
            // Batas bayar tagihan susulan = tanggal terbit + N hari.
            $table->unsignedTinyInteger('batas_bayar_susulan_hari')->default(3);
        });

        Schema::create('pengajuan_susulan', function (Blueprint $table) {
            $table->id();
            // Ujian utama (UTS/UAS) yang tidak diikuti.
            $table->foreignId('ujian_id')->constrained('ujians')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            $table->text('alasan');
            $table->json('lampiran');
            // menunggu | disetujui | ditolak | dibatalkan
            $table->string('status', 20)->default('menunggu');
            $table->string('catatan_admin', 1000)->nullable();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->timestamps();

            $table->index(['ujian_id', 'mahasiswa_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_susulan');

        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            $table->dropColumn(['batas_pengajuan_susulan_hari', 'batas_bayar_susulan_hari']);
        });
    }
};
