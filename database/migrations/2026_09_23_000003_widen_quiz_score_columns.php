<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * decimal(5,2) hanya menampung sampai 999.99: quiz dengan total poin >= 1000 gagal disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->decimal('score', 8, 2)->nullable()->change());
        Schema::table('quiz_answers', fn (Blueprint $table) => $table->decimal('point', 8, 2)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->decimal('score', 5, 2)->nullable()->change());
        Schema::table('quiz_answers', fn (Blueprint $table) => $table->decimal('point', 5, 2)->nullable()->change());
    }
};
