<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // semester = ikut tagihan semester; remidi = hanya dipakai tagihan remidi (per mata kuliah).
        Schema::table('jenis_biaya', function (Blueprint $table) {
            $table->string('kategori', 10)->default('semester')->after('cara_hitung');
        });

        // Lewat tanggal ini tagihan remidi yang belum lunas gugur dan bukti tidak bisa diunggah lagi.
        Schema::table('tahun_akademik', function (Blueprint $table) {
            $table->date('batas_bayar_remidi')->nullable()->after('batas_input_nilai');
        });

        Schema::create('tagihan_remidi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remidi_peserta_id')->unique()->constrained('remidi_pesertas')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas_kuliah')->restrictOnDelete();
            // Rincian dibekukan saat terbit: [{nama, cara_hitung, nominal_satuan, jumlah, subtotal}].
            $table->json('rincian');
            $table->unsignedBigInteger('total')->default(0);
            // belum_bayar | menunggu_verifikasi | lunas | ditolak
            $table->string('status', 20)->default('belum_bayar');
            // Kolom bukti & verifikasi sengaja berpola umum agar bisa disalin ke tagihan semester nanti.
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
        Schema::dropIfExists('tagihan_remidi');

        Schema::table('tahun_akademik', function (Blueprint $table) {
            $table->dropColumn('batas_bayar_remidi');
        });

        Schema::table('jenis_biaya', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
};
