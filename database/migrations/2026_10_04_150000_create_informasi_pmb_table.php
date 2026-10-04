<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Isi halaman publik Informasi PMB (/pmb), satu baris (singleton id 1). Jadwal periode diambil dari pengaturan_pmb.
        Schema::create('informasi_pmb', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('judul', 150)->default('Penerimaan Mahasiswa Baru');
            $table->text('pengantar')->nullable();
            $table->text('syarat')->nullable();
            $table->text('jadwal_tes')->nullable();
            $table->text('biaya')->nullable();
            $table->text('kontak')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('informasi_pmb');
        PermissionCatalog::sync();
    }
};
