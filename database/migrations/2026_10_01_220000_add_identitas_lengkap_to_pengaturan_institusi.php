<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_institusi', function (Blueprint $table): void {
            $table->foreignId('badan_hukum_id')->nullable()->after('nama_pt')->constrained('badan_hukum')->restrictOnDelete();
            $table->string('nomor_akta_terakhir', 100)->nullable()->after('tahun_berdiri');
            $table->date('tanggal_akta_terakhir')->nullable()->after('nomor_akta_terakhir');
            $table->string('nomor_pengesahan', 100)->nullable()->after('tanggal_akta_terakhir');
            $table->date('tanggal_pengesahan')->nullable()->after('nomor_pengesahan');
            $table->string('akreditasi', 50)->nullable()->after('tanggal_pengesahan');
            $table->text('alamat_lain')->nullable()->after('alamat');
            $table->foreignId('provinsi_id')->nullable()->after('alamat_lain')->constrained('provinsis')->restrictOnDelete();
            $table->foreignId('kota_id')->nullable()->after('provinsi_id')->constrained('kotas')->restrictOnDelete();
            $table->string('kode_pos', 10)->nullable()->after('kota_id');
            $table->string('faximili', 30)->nullable()->after('telepon');
        });

        // Nama izin admin.institusi ikut berganti menjadi "Perguruan Tinggi" (menu pindah ke Master).
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('pengaturan_institusi', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('badan_hukum_id');
            $table->dropConstrainedForeignId('provinsi_id');
            $table->dropConstrainedForeignId('kota_id');
            $table->dropColumn([
                'nomor_akta_terakhir', 'tanggal_akta_terakhir', 'nomor_pengesahan', 'tanggal_pengesahan', 'akreditasi',
                'alamat_lain', 'kode_pos', 'faximili',
            ]);
        });
        PermissionCatalog::sync();
    }
};
