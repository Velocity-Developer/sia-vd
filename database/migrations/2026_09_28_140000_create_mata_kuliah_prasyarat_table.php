<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prasyarat mata kuliah: mata kuliah hanya bisa diambil di KRS bila semua prasyaratnya sudah lulus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mata_kuliah_prasyarat', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliahs')->cascadeOnDelete();
            $table->foreignId('prasyarat_id')->constrained('mata_kuliahs')->cascadeOnDelete();
            $table->unique(['mata_kuliah_id', 'prasyarat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_kuliah_prasyarat');
    }
};
