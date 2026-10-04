<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Pengajuan & Pendaftaran: naskah TA yang diunggah mahasiswa sesudah judulnya disahkan (Pengajuan Judul & Upload TA),
 * plus nama/deskripsi izin yang diperbarui (KKM kini Kuliah Kerja Mahasiswa: KKM/PKL/KKN).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tugas_akhir', function (Blueprint $table): void {
            $table->string('naskah')->nullable()->after('status');
            $table->timestamp('naskah_diunggah_at')->nullable()->after('naskah');
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('tugas_akhir', function (Blueprint $table): void {
            $table->dropColumn(['naskah', 'naskah_diunggah_at']);
        });

        PermissionCatalog::sync();
    }
};
