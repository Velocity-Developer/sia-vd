<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_institusi', function (Blueprint $table): void {
            $table->string('zona_waktu', 40)->default('Asia/Jakarta')->after('tahun_berdiri');
        });

        // Data institusi yang dibagikan ke halaman di-cache tanpa kolom baru ini.
        Cache::forget('institusi.shared');
    }

    public function down(): void
    {
        Schema::table('pengaturan_institusi', function (Blueprint $table): void {
            $table->dropColumn('zona_waktu');
        });
    }
};
