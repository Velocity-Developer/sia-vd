<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skala_nilais', function (Blueprint $table) {
            // Batas bawah nilai angka (0–100) untuk huruf ini; dipakai mengonversi nilai pendadaran.
            $table->decimal('angka_minimal', 5, 2)->nullable()->after('bobot');
        });
        foreach (['A' => 80, 'B' => 70, 'C' => 60, 'D' => 50, 'E' => 0] as $huruf => $angka) {
            DB::table('skala_nilais')->where('huruf', $huruf)->update(['angka_minimal' => $angka]);
        }

        Schema::table('pendadaran', function (Blueprint $table) {
            $table->string('nomor_surat', 60)->nullable()->after('mahasiswa_id');
            // Rata-rata nilai ketiga penguji dan hurufnya menurut skala nilai.
            $table->decimal('nilai_akhir', 5, 2)->nullable()->after('status');
            $table->string('huruf', 5)->nullable()->after('nilai_akhir');
            // lulus | lulus_revisi | tidak_lulus, ditetapkan ketua penguji.
            $table->string('hasil', 20)->nullable()->after('huruf');
            $table->text('catatan_hasil')->nullable()->after('hasil');
            $table->timestamp('hasil_ditetapkan_at')->nullable()->after('catatan_hasil');
            $table->string('naskah_revisi')->nullable()->after('hasil_ditetapkan_at');
            $table->timestamp('revisi_diunggah_at')->nullable()->after('naskah_revisi');
            $table->string('catatan_revisi', 1000)->nullable()->after('revisi_diunggah_at');
            $table->timestamp('revisi_disahkan_at')->nullable()->after('catatan_revisi');
        });

        Schema::create('nilai_pendadaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendadaran_id')->constrained('pendadaran')->cascadeOnDelete();
            // 1 = ketua, 2, 3.
            $table->unsignedTinyInteger('penguji_ke');
            $table->foreignId('dosen_id')->constrained('dosen_profiles')->restrictOnDelete();
            $table->decimal('nilai', 5, 2);
            $table->string('catatan', 1000)->nullable();
            $table->timestamps();

            $table->unique(['pendadaran_id', 'penguji_ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_pendadaran');

        Schema::table('pendadaran', function (Blueprint $table) {
            $table->dropColumn(['nomor_surat', 'nilai_akhir', 'huruf', 'hasil', 'catatan_hasil', 'hasil_ditetapkan_at', 'naskah_revisi', 'revisi_diunggah_at', 'catatan_revisi', 'revisi_disahkan_at']);
        });

        Schema::table('skala_nilais', function (Blueprint $table) {
            $table->dropColumn('angka_minimal');
        });
    }
};
