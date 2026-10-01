<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris saja (id 1), diubah lewat menu Master → Badan Hukum.
        Schema::create('badan_hukum', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nama_badan_hukum')->nullable();
            $table->date('tanggal_berdiri')->nullable();
            $table->string('nomor_akta_terakhir', 100)->nullable();
            $table->date('tanggal_akta_terakhir')->nullable();
            $table->string('nomor_pengesahan', 100)->nullable();
            $table->date('tanggal_pengesahan')->nullable();
            $table->string('alamat_jalan', 500)->nullable();
            $table->foreignId('provinsi_id')->nullable()->constrained('provinsis')->restrictOnDelete();
            $table->foreignId('kota_id')->nullable()->constrained('kotas')->restrictOnDelete();
            $table->string('kode_pos', 10)->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('faximili', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('badan_hukum');
        PermissionCatalog::sync();
    }
};
