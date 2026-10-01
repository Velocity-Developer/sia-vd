<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris pengaturan Google reCAPTCHA v2 untuk halaman masuk; bawaan mati.
        Schema::create('pengaturan_recaptcha', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->boolean('aktif')->default(false);
            $table->string('site_key')->nullable();
            // Disimpan terenkripsi (cast 'encrypted' pada model).
            $table->text('secret_key')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Izin baru admin.pengaturan-recaptcha.
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_recaptcha');
    }
};
