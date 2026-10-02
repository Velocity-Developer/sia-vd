<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Presensi dosen per pertemuan: status kehadiran dosen (dikoreksi admin), verifikasi presensi oleh admin, dan
 * riwayat koreksi/verifikasi. Izin menu Presensi Dosen dan Verifikasi Presensi Dosen ikut disinkronkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pertemuans', function (Blueprint $table): void {
            $table->string('status_dosen', 20)->nullable()->after('dosen_keluar_at');
            $table->string('verifikasi', 10)->nullable()->after('status_dosen');
            $table->string('catatan_verifikasi')->nullable()->after('verifikasi');
            $table->foreignId('diverifikasi_oleh')->nullable()->after('catatan_verifikasi')->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable()->after('diverifikasi_oleh');
        });

        Schema::create('riwayat_presensi_dosens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pertemuan_id')->constrained('pertemuans')->cascadeOnDelete();
            $table->string('aksi', 20);
            $table->json('perubahan')->nullable();
            $table->string('alasan')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Pertemuan yang sudah selesai sebelum kolom ini ada: hadir, atau digantikan bila diajar dosen lain.
        DB::table('pertemuans')->where('status', 'selesai')->update(['status_dosen' => 'hadir']);
        DB::table('pertemuans')->where('status', 'selesai')->whereNotNull('dosen_id')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('kelas_kuliah')
                ->whereColumn('kelas_kuliah.id', 'pertemuans.kelas_id')->whereColumn('kelas_kuliah.dosen_id', '!=', 'pertemuans.dosen_id'))
            ->update(['status_dosen' => 'digantikan']);

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_presensi_dosens');

        Schema::table('pertemuans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('diverifikasi_oleh');
            $table->dropColumn(['status_dosen', 'verifikasi', 'catatan_verifikasi', 'diverifikasi_at']);
        });

        PermissionCatalog::sync();
    }
};
