<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pertemuan tidak lagi dibatalkan atau disusun ulang dari jadwal mingguan; perubahan jadwal hanya lewat
 * edit per pertemuan dengan alasan, dan setiap perubahan dicatat di riwayat_jadwal_pertemuan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dibatalkan = DB::table('pertemuans')->where('status', 'dibatalkan')->count();
        if ($dibatalkan > 0) {
            throw new RuntimeException("Masih ada {$dibatalkan} pertemuan berstatus dibatalkan. Pindahkan ke tanggal pengganti (ubah status menjadi dijadwalkan) sebelum migrasi.");
        }

        Schema::create('riwayat_jadwal_pertemuan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pertemuan_id')->constrained('pertemuans')->cascadeOnDelete();
            $table->date('tanggal_lama');
            $table->time('jam_mulai_lama');
            $table->time('jam_akhir_lama');
            $table->foreignId('ruang_lama_id')->nullable()->constrained('ruangs')->nullOnDelete();
            $table->foreignId('dosen_lama_id')->nullable()->constrained('dosen_profiles')->nullOnDelete();
            $table->date('tanggal_baru');
            $table->time('jam_mulai_baru');
            $table->time('jam_akhir_baru');
            $table->foreignId('ruang_baru_id')->nullable()->constrained('ruangs')->nullOnDelete();
            $table->foreignId('dosen_baru_id')->nullable()->constrained('dosen_profiles')->nullOnDelete();
            $table->string('alasan', 255);
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pertemuan_id', 'created_at']);
        });

        Schema::table('pertemuans', fn (Blueprint $table) => $table->dropColumn('jadwal_manual'));
    }

    public function down(): void
    {
        Schema::table('pertemuans', fn (Blueprint $table) => $table->boolean('jadwal_manual')->default(false)->after('jadwal_id'));
        Schema::dropIfExists('riwayat_jadwal_pertemuan');
    }
};
