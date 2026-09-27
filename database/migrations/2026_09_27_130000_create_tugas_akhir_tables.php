<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_kuliahs', function (Blueprint $table) {
            // Mata kuliah TA/Skripsi: syarat pengajuan TA dan pendadaran adalah sedang mengambilnya.
            $table->boolean('tugas_akhir')->default(false)->after('jenis');
        });

        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            // SKS bernilai di transkrip (tanpa mata kuliah TA) minimal untuk mendaftar pendadaran.
            $table->unsignedSmallInteger('min_sks_pendadaran')->default(138);
        });

        Schema::create('pengajuan_akademik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            // tugas_akhir | pendadaran | wisuda
            $table->string('jenis', 20);
            // Isian form per jenis (judul, ringkasan, usulan pembimbing, ...).
            $table->json('isian');
            // Berkas per kolom form: {"proposal": "pengajuan-akademik/xxx.pdf"}.
            $table->json('lampiran');
            // menunggu | perlu_perbaikan | disetujui | ditolak
            $table->string('status', 30)->default('menunggu');
            // Catatan terakhir pemroses (alasan perbaikan/penolakan); riwayat lengkap di riwayat_pengajuan_akademik.
            $table->string('catatan', 1000)->nullable();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            // Waktu kirim terakhir (berubah saat perbaikan dikirim ulang).
            $table->timestamp('diajukan_at')->nullable();
            $table->timestamps();

            $table->index(['mahasiswa_id', 'jenis', 'status']);
            $table->index(['jenis', 'status']);
        });

        Schema::create('riwayat_pengajuan_akademik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_akademik_id')->constrained('pengajuan_akademik')->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('catatan', 1000)->nullable();
            $table->foreignId('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tugas_akhir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            $table->foreignId('pengajuan_id')->nullable()->constrained('pengajuan_akademik')->nullOnDelete();
            $table->string('judul', 300);
            $table->string('bidang', 150);
            $table->foreignId('pembimbing_1_id')->constrained('dosen_profiles')->restrictOnDelete();
            $table->foreignId('pembimbing_2_id')->nullable()->constrained('dosen_profiles')->restrictOnDelete();
            // berjalan | selesai
            $table->string('status', 20)->default('berjalan');
            $table->foreignId('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();

            $table->index(['mahasiswa_id', 'status']);
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_akhir');
        Schema::dropIfExists('riwayat_pengajuan_akademik');
        Schema::dropIfExists('pengajuan_akademik');

        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            $table->dropColumn('min_sks_pendadaran');
        });

        Schema::table('mata_kuliahs', function (Blueprint $table) {
            $table->dropColumn('tugas_akhir');
        });
    }
};
