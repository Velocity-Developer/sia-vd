<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Isi surel sistem yang diubah admin; jenis yang belum punya baris memakai isi bawaan (TemplateEmail::JENIS).
        Schema::create('template_email', function (Blueprint $table) {
            $table->string('jenis', 50)->primary();
            $table->string('subjek');
            $table->string('sapaan')->nullable();
            $table->text('isi');
            $table->string('tombol', 100)->nullable();
            $table->string('penutup', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_email');
    }
};
