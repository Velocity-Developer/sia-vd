<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Override status fitur per klien dari database (lihat App\Feature), beserta riwayat perubahannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_fitur', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 50)->unique();
            $table->boolean('aktif');
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('log_pengaturan_fitur', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 50);
            // null = tanpa override (ikut bawaan config).
            $table->boolean('aktif_lama')->nullable();
            $table->boolean('aktif_baru')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['nama', 'created_at']);
        });

        Cache::forget('fitur.override');
    }

    public function down(): void
    {
        Schema::dropIfExists('log_pengaturan_fitur');
        Schema::dropIfExists('pengaturan_fitur');
        Cache::forget('fitur.override');
    }
};
