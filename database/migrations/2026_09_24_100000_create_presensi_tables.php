<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            // Nilai bawaan untuk kelas baru; tiap kelas menyimpan jumlahnya sendiri.
            $table->unsignedTinyInteger('jumlah_pertemuan')->default(16)->after('kunci_krs_aktif');
            $table->unsignedTinyInteger('min_kehadiran_ujian')->default(75)->after('jumlah_pertemuan');
            $table->unsignedSmallInteger('toleransi_terlambat_menit')->default(15)->after('min_kehadiran_ujian');
            $table->unsignedSmallInteger('durasi_presensi_mandiri_menit')->default(15)->after('toleransi_terlambat_menit');
            $table->unsignedTinyInteger('batas_pengajuan_izin_hari')->default(1)->after('durasi_presensi_mandiri_menit');
            // Mati secara bawaan: status "tidak memenuhi syarat ujian" baru ditampilkan bila kampus siap memakainya.
            $table->boolean('syarat_ujian_aktif')->default(false)->after('batas_pengajuan_izin_hari');
        });

        Schema::table('kelas_kuliah', function (Blueprint $table) {
            // Kelas yang sudah ada ikut bawaan 16 pertemuan.
            $table->unsignedTinyInteger('jumlah_pertemuan')->default(16)->after('kapasitas');
        });

        Schema::create('pertemuans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas_kuliah')->restrictOnDelete();
            $table->unsignedTinyInteger('pertemuan_ke');
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_akhir');
            $table->foreignId('ruang_id')->nullable()->constrained('ruangs')->nullOnDelete();
            $table->string('jenis', 10)->default('kuliah');
            $table->string('status', 15)->default('dijadwalkan');
            // Dosen yang benar-benar mengajar; bisa berbeda dari pengampu kelas (dosen pengganti).
            $table->foreignId('dosen_id')->nullable()->constrained('dosen_profiles')->nullOnDelete();
            $table->timestamp('dosen_masuk_at')->nullable();
            $table->timestamp('dosen_keluar_at')->nullable();
            $table->text('topik')->nullable();
            $table->string('catatan', 255)->nullable();
            // Presensi mandiri (QR/PIN): kode diturunkan dari rahasia ini dan berganti tiap 30 detik.
            $table->string('kode_rahasia', 64)->nullable();
            $table->timestamp('mandiri_sampai')->nullable();
            $table->timestamps();

            $table->unique(['kelas_id', 'pertemuan_ke']);
            $table->index(['tanggal', 'status']);
        });

        Schema::create('presensi_mahasiswas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pertemuan_id')->constrained('pertemuans')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            $table->string('status', 10)->default('alpa');
            $table->timestamp('waktu_presensi')->nullable();
            $table->string('metode', 10)->default('manual');
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('keterangan', 255)->nullable();
            // Jejak presensi mandiri untuk menandai titip absen (satu perangkat dipakai beberapa mahasiswa).
            $table->string('ip', 45)->nullable();
            $table->string('perangkat', 64)->nullable();
            $table->timestamps();

            $table->unique(['pertemuan_id', 'mahasiswa_id']);
            $table->index(['mahasiswa_id', 'status']);
        });

        Schema::create('pengajuan_izins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pertemuan_id')->constrained('pertemuans')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            $table->string('jenis', 10);
            $table->text('alasan');
            $table->json('lampiran')->nullable();
            $table->string('status', 10)->default('menunggu');
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->string('catatan_dosen', 255)->nullable();
            $table->timestamps();

            // Satu pengajuan per pertemuan; pengajuan yang ditolak bisa diajukan ulang (baris yang sama diperbarui).
            $table->unique(['pertemuan_id', 'mahasiswa_id']);
            $table->index('status');
        });

        Schema::create('dispensasi_ujians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas_kuliah')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->restrictOnDelete();
            $table->string('jenis', 5);
            $table->string('alasan', 255);
            $table->foreignId('diberikan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kelas_id', 'mahasiswa_id', 'jenis']);
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensasi_ujians');
        Schema::dropIfExists('pengajuan_izins');
        Schema::dropIfExists('presensi_mahasiswas');
        Schema::dropIfExists('pertemuans');

        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->dropColumn('jumlah_pertemuan');
        });

        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            $table->dropColumn(['jumlah_pertemuan', 'min_kehadiran_ujian', 'toleransi_terlambat_menit', 'durasi_presensi_mandiri_menit', 'batas_pengajuan_izin_hari', 'syarat_ujian_aktif']);
        });
    }
};
