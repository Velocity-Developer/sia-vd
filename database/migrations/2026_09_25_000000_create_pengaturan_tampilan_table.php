<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris pengaturan tampilan; kolom kosong berarti memakai bawaan aplikasi.
        Schema::create('pengaturan_tampilan', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nama_aplikasi', 100)->nullable();
            $table->string('favicon')->nullable();
            $table->string('login_judul', 120)->nullable();
            $table->string('login_teks', 300)->nullable();
            $table->string('login_gambar')->nullable();
            $table->boolean('login_sorotan')->default(true);
            $table->string('sidebar_bawaan', 10)->default('lebar');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Izin baru admin.pengaturan-tampilan, dan izin pengaturan lain pindah ke grup "Pengaturan Sistem".
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_tampilan');
    }
};
