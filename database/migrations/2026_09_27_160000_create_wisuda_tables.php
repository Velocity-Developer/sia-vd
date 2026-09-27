<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_wisuda', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->date('tanggal_acara');
            $table->string('tempat', 200)->nullable();
            // Pendaftaran dibuka sampai akhir hari ini.
            $table->date('batas_daftar');
            // Kosong = tanpa batas.
            $table->unsignedInteger('kuota')->nullable();
            $table->timestamps();
        });

        Schema::create('wisuda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_akademik')->restrictOnDelete();
            $table->foreignId('periode_wisuda_id')->constrained('periode_wisuda')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->unique()->constrained('mahasiswa_profiles')->restrictOnDelete();
            $table->foreignId('tugas_akhir_id')->constrained('tugas_akhir')->restrictOnDelete();
            // Surat keterangan lulus: data dibekukan saat terbit.
            $table->string('nomor_skl', 60)->nullable()->unique();
            $table->timestamp('skl_terbit_at')->nullable();
            $table->date('tanggal_lulus')->nullable();
            $table->decimal('ipk', 3, 2)->nullable();
            $table->unsignedSmallInteger('total_sks')->nullable();
            $table->string('predikat', 60)->nullable();
            $table->foreignId('skl_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wisuda');
        Schema::dropIfExists('periode_wisuda');
    }
};
