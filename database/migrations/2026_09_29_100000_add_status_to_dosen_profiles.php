<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status dosen (Aktif/Nonaktif). Dosen nonaktif tidak dapat masuk.
     */
    public function up(): void
    {
        Schema::table('dosen_profiles', function (Blueprint $table) {
            $table->string('status', 20)->default('Aktif')->after('status_kepegawaian');
        });
    }

    public function down(): void
    {
        Schema::table('dosen_profiles', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
