<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            // Quiz yang dipakai sebagai lembar soal ujian online (mode soal di sistem); tidak tampil di menu Quiz.
            $table->foreignId('ujian_id')->nullable()->unique()->after('kelas_id')->constrained('ujians')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ujian_id');
        });
    }
};
