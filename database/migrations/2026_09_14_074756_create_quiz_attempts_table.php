<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa_profiles')->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['quiz_id', 'mahasiswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
