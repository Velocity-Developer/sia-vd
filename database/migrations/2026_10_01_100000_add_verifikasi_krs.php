<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // KRS yang disimpan mahasiswa kini berstatus: diajukan → disetujui, atau dikembalikan untuk revisi.
        Schema::table('krs_semester', function (Blueprint $table) {
            $table->string('status', 20)->default('diajukan')->after('tahun_akademik_id');
            $table->text('catatan_revisi')->nullable()->after('disimpan_pada');
            // Batas buka kunci khusus mahasiswa ini; mengalahkan masa revisi semester.
            $table->date('dibuka_sampai')->nullable()->after('catatan_revisi');
            $table->foreignId('diverifikasi_oleh')->nullable()->after('dibuka_sampai')->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable()->after('diverifikasi_oleh');

            $table->index(['tahun_akademik_id', 'status']);
        });

        // KRS yang sudah tersimpan sebelum fitur ini sudah terkunci final.
        DB::table('krs_semester')->update(['status' => 'disetujui']);

        Schema::table('tahun_akademik', function (Blueprint $table) {
            $table->date('tanggal_revisi_krs_akhir')->nullable()->after('tanggal_krs_akhir');
        });

        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            // Mati secara bawaan: KRS yang disimpan langsung final seperti sebelumnya.
            $table->boolean('verifikasi_krs_aktif')->default(false)->after('kunci_krs_aktif');
        });

        // Izin baru admin.verifikasi-krs.
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('pengaturan_akademik', function (Blueprint $table) {
            $table->dropColumn('verifikasi_krs_aktif');
        });

        Schema::table('tahun_akademik', function (Blueprint $table) {
            $table->dropColumn('tanggal_revisi_krs_akhir');
        });

        Schema::table('krs_semester', function (Blueprint $table) {
            $table->dropIndex(['tahun_akademik_id', 'status']);
            $table->dropConstrainedForeignId('diverifikasi_oleh');
            $table->dropColumn(['status', 'catatan_revisi', 'dibuka_sampai', 'diverifikasi_pada']);
        });

        DB::table('permissions')->where('key', 'admin.verifikasi-krs')->delete();
    }
};
