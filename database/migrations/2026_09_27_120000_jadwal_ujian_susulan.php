<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jenis ujian susulan (uts_susulan / uas_susulan) lebih panjang dari 10 karakter.
        Schema::table('ujians', function (Blueprint $table) {
            $table->string('jenis', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ujians', function (Blueprint $table) {
            $table->string('jenis', 10)->change();
        });
    }
};
