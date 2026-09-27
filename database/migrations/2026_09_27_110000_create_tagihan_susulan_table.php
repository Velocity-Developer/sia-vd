<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_susulan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_susulan_id')->unique()->constrained('pengajuan_susulan')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            // Ujian utama (UTS/UAS) yang disusul.
            $table->foreignId('ujian_id')->constrained('ujians')->restrictOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas_kuliah')->restrictOnDelete();
            // Rincian dibekukan saat terbit: [{nama, cara_hitung, nominal_satuan, jumlah, subtotal}].
            $table->json('rincian');
            $table->unsignedBigInteger('total')->default(0);
            // belum_bayar | menunggu_verifikasi | lunas | ditolak (gugur & dibatalkan = status tampilan)
            $table->string('status', 20)->default('belum_bayar');
            // Per tagihan: tanggal terbit + batas_bayar_susulan_hari.
            $table->date('batas_bayar');
            $table->string('bukti')->nullable();
            $table->timestamp('bukti_diunggah_at')->nullable();
            $table->string('alasan_tolak', 255)->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->foreignId('diterbitkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'kelas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_susulan');
    }
};
