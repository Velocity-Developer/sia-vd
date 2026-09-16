<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pengajuan_pindah_kelas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->cascadeOnDelete();
            $table->foreignId('kelas_asal_id')->constrained('kelas_kuliah')->cascadeOnDelete();
            $table->foreignId('kelas_tujuan_id')->constrained('kelas_kuliah')->cascadeOnDelete();
            $table->text('alasan');
            $table->string('status')->default('pending');
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_pindah_kelas');
    }
};
