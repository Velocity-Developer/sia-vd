<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_akademik', function (Blueprint $table) {
            // Pendadaran/wisuda terikat ke TA yang sudah disahkan.
            $table->foreignId('tugas_akhir_id')->nullable()->after('jenis')->constrained('tugas_akhir')->nullOnDelete();
            // Pendaftaran pendadaran disetujui salah satu pembimbing dulu, baru naik ke admin.
            $table->foreignId('disetujui_pembimbing_oleh')->nullable()->after('diajukan_at')->constrained('dosen_profiles')->nullOnDelete();
            $table->timestamp('disetujui_pembimbing_at')->nullable()->after('disetujui_pembimbing_oleh');
        });

        Schema::create('pendadaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_akademik')->restrictOnDelete();
            $table->foreignId('tugas_akhir_id')->constrained('tugas_akhir')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_akhir');
            $table->foreignId('ruang_id')->constrained('ruangs')->restrictOnDelete();
            // Penguji 1 = ketua penguji.
            $table->foreignId('penguji_1_id')->constrained('dosen_profiles')->restrictOnDelete();
            $table->foreignId('penguji_2_id')->constrained('dosen_profiles')->restrictOnDelete();
            $table->foreignId('penguji_3_id')->constrained('dosen_profiles')->restrictOnDelete();
            // dijadwalkan | selesai
            $table->string('status', 20)->default('dijadwalkan');
            $table->foreignId('dijadwalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tanggal', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendadaran');

        Schema::table('pengajuan_akademik', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tugas_akhir_id');
            $table->dropConstrainedForeignId('disetujui_pembimbing_oleh');
            $table->dropColumn('disetujui_pembimbing_at');
        });
    }
};
