<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agamas', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama')->unique();
            $table->timestamps();
        });

        Schema::create('provinsis', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama')->unique();
            $table->timestamps();
        });

        Schema::create('kotas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provinsi_id')->constrained('provinsis')->restrictOnDelete();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->timestamps();

            $table->unique(['provinsi_id', 'nama']);
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('kotas');
        Schema::dropIfExists('provinsis');
        Schema::dropIfExists('agamas');
        PermissionCatalog::sync();
    }
};
