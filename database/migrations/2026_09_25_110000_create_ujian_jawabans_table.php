<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujians', function (Blueprint $table) {
            // Mode online unggah berkas: berkas soal di disk privat, baru bisa diunduh mahasiswa saat ujian dimulai.
            $table->json('soal_berkas')->nullable()->after('petunjuk');
            // Nilai baru terlihat mahasiswa setelah dosen merilisnya.
            $table->boolean('nilai_dirilis')->default(false)->after('soal_berkas');
        });

        Schema::create('ujian_jawabans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujians')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            $table->json('berkas');
            $table->timestamp('dikumpulkan_at');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->string('catatan_dosen', 1000)->nullable();
            $table->foreignId('dinilai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ujian_id', 'mahasiswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_jawabans');

        Schema::table('ujians', function (Blueprint $table) {
            $table->dropColumn(['soal_berkas', 'nilai_dirilis']);
        });
    }
};
