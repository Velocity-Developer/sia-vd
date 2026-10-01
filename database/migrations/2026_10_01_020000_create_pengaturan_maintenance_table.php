<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris pengaturan mode maintenance per jenis pengguna (dosen/mahasiswa); admin tidak pernah kena.
        Schema::create('pengaturan_maintenance', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->boolean('aktif')->default(false);
            $table->boolean('untuk_dosen')->default(true);
            $table->boolean('untuk_mahasiswa')->default(true);
            $table->string('pesan', 500)->nullable();
            // Jam dinding institusi, sama seperti kolom waktu lain (lihat TerapkanZonaWaktu).
            $table->dateTime('perkiraan_selesai')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Izin baru admin.pengaturan-maintenance.
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_maintenance');
    }
};
