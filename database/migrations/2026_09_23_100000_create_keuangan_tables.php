<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_biaya', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            // tetap = nominal per semester, per_sks = nominal dikali jumlah SKS yang diambil.
            $table->string('cara_hitung')->default('tetap');
            $table->string('keterangan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        // Tarif dipisah dari jenisnya karena nominalnya berbeda per program studi dan angkatan.
        // prodi_id/angkatan kosong berarti berlaku untuk semua.
        Schema::create('tarif_biaya', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_biaya_id')->constrained('jenis_biaya')->cascadeOnDelete();
            $table->foreignId('prodi_id')->nullable()->constrained('program_studis')->cascadeOnDelete();
            $table->unsignedSmallInteger('angkatan')->nullable();
            $table->unsignedBigInteger('nominal')->default(0);
            $table->timestamps();

            $table->unique(['jenis_biaya_id', 'prodi_id', 'angkatan'], 'tarif_biaya_unik');
        });

        Schema::create('tagihan_semester', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->cascadeOnDelete();
            // Disimpan sebagai string, bukan enum, agar status baru (mis. sebagian) tidak perlu ubah kolom.
            $table->string('status')->default('belum_bayar');
            $table->unsignedBigInteger('total')->default(0);
            $table->date('tanggal_lunas')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mahasiswa_id', 'tahun_akademik_id'], 'tagihan_semester_unik');
        });

        // Rincian dibekukan saat tagihan dibuat: nama dan nominalnya disalin, sehingga
        // mengubah tarif tidak mengacak tagihan yang sudah terbit.
        Schema::create('tagihan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan_semester')->cascadeOnDelete();
            $table->foreignId('jenis_biaya_id')->nullable()->constrained('jenis_biaya')->nullOnDelete();
            $table->string('nama');
            $table->string('cara_hitung')->default('tetap');
            $table->unsignedBigInteger('nominal_satuan')->default(0);
            $table->unsignedSmallInteger('jumlah')->default(1);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_item');
        Schema::dropIfExists('tagihan_semester');
        Schema::dropIfExists('tarif_biaya');
        Schema::dropIfExists('jenis_biaya');
    }
};
