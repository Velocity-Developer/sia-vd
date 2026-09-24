<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_tampilan', function (Blueprint $table) {
            // panel = panel merek di kiri + form di kanan (bawaan); tengah = kartu masuk di tengah layar.
            $table->string('login_tata_letak', 10)->default('panel')->after('login_sorotan');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_tampilan', function (Blueprint $table) {
            $table->dropColumn('login_tata_letak');
        });
    }
};
