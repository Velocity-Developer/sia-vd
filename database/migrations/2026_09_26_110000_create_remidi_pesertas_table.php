<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daftar peserta remidi disusun dosen setelah nilai kelas final, lalu dikunci sebelum tagihan remidi terbit.
        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->timestamp('remidi_dikunci_at')->nullable();
            $table->foreignId('remidi_dikunci_oleh')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('remidi_pesertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas_kuliah')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            // Huruf akhir saat daftar dikunci, sebagai pembanding setelah remidi.
            $table->string('nilai_awal', 5)->nullable();
            // true = masuk usulan otomatis; false = ditambahkan dosen.
            $table->boolean('diusulkan')->default(false);
            $table->timestamps();

            $table->unique(['kelas_id', 'mahasiswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remidi_pesertas');

        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->dropConstrainedForeignId('remidi_dikunci_oleh');
            $table->dropColumn('remidi_dikunci_at');
        });
    }
};
