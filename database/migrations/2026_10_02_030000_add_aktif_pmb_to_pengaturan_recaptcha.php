<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Captcha formulir PMB dinyalakan terpisah dari halaman masuk; kuncinya sama.
        Schema::table('pengaturan_recaptcha', function (Blueprint $table): void {
            $table->boolean('aktif_pmb')->default(false)->after('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_recaptcha', function (Blueprint $table): void {
            $table->dropColumn('aktif_pmb');
        });
    }
};
