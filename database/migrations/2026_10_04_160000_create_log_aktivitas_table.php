<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jejak perubahan data dan login oleh pengguna (Pengguna & Akses → Log Aktivitas).
        Schema::create('log_aktivitas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 20);
            $table->string('objek_tipe', 100)->nullable();
            $table->unsignedBigInteger('objek_id')->nullable();
            $table->string('label')->nullable();
            $table->json('perubahan')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('rute', 150)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['objek_tipe', 'objek_id']);
            $table->index(['user_id', 'created_at']);
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
        PermissionCatalog::sync();
    }
};
