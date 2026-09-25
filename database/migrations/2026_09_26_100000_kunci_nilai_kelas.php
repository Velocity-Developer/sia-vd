<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lewat tanggal ini nilai semua kelas di tahun akademik terkunci untuk dosen. Kosong = tanpa batas.
        Schema::table('tahun_akademik', function (Blueprint $table) {
            $table->date('batas_input_nilai')->nullable()->after('tanggal_krs_akhir');
        });

        Schema::table('kelas_kuliah', function (Blueprint $table) {
            // Diisi saat dosen/admin memfinalisasi nilai kelas; dikosongkan lagi bila admin membuka kunci.
            $table->timestamp('nilai_final_at')->nullable();
            $table->foreignId('nilai_final_oleh')->nullable()->constrained('users')->nullOnDelete();
            // Batas pengganti per kelas saat admin membuka kunci setelah batas input nilai tahun akademik lewat.
            $table->date('nilai_dibuka_sampai')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nilai_final_oleh');
            $table->dropColumn(['nilai_final_at', 'nilai_dibuka_sampai']);
        });

        Schema::table('tahun_akademik', function (Blueprint $table) {
            $table->dropColumn('batas_input_nilai');
        });
    }
};
