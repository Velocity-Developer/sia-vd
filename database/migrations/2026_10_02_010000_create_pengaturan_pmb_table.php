<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periode penerimaan calon mahasiswa baru (PMB).
        Schema::create('pengaturan_pmb', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->unsignedSmallInteger('tahun_angkatan');
            $table->date('tanggal_buka');
            $table->date('tanggal_tutup');
            $table->date('tanggal_usm_mulai');
            $table->date('tanggal_usm_selesai');
            $table->date('tanggal_her');
            $table->decimal('nilai_minimal', 5, 2)->default(0);
            $table->unsignedInteger('kapasitas');
            $table->unsignedBigInteger('biaya_pendaftaran')->default(0);
            $table->date('tanggal_pembayaran_mulai');
            $table->date('tanggal_pembayaran_selesai');
            $table->boolean('is_open')->default(false);
            $table->timestamps();
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_pmb');
        PermissionCatalog::sync();
    }
};
