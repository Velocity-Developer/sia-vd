<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ujian remidi (per kelas, sekali) memakai tabel ujians dengan jenis 'remidi'.
        Schema::table('ujians', function (Blueprint $table) {
            $table->string('jenis', 10)->change();
        });

        // Jadwal remidi harus jatuh sebelum tanggal ini; nilai remidi & huruf akhir peserta terkunci sesudahnya.
        Schema::table('tahun_akademik', function (Blueprint $table) {
            $table->date('batas_input_nilai_remidi')->nullable()->after('batas_bayar_remidi');
        });
    }

    public function down(): void
    {
        Schema::table('tahun_akademik', function (Blueprint $table) {
            $table->dropColumn('batas_input_nilai_remidi');
        });

        Schema::table('ujians', function (Blueprint $table) {
            $table->string('jenis', 5)->change();
        });
    }
};
