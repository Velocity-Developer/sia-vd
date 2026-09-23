<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penanda KRS yang sudah disimpan mahasiswa. Sesudah tersimpan, isi KRS hanya bisa
        // diubah lewat pengajuan pindah kelas atau setelah admin membuka kuncinya.
        Schema::create('krs_semester', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->cascadeOnDelete();
            $table->timestamp('disimpan_pada')->nullable();
            $table->timestamps();

            $table->unique(['mahasiswa_id', 'tahun_akademik_id'], 'krs_semester_unik');
        });

        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            // Mati secara bawaan: penguncian baru berlaku saat kampus memang siap memakainya.
            $table->boolean('kunci_krs_aktif')->default(false)->after('maks_sks_tanpa_ips');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            $table->dropColumn('kunci_krs_aktif');
        });

        Schema::dropIfExists('krs_semester');
    }
};
