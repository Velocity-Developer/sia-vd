<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kurikulum prodi: daftar mata kuliah (termasuk TA/Skripsi & PPL) beserta semester dan sifat wajib/pilihannya.
        Schema::create('kurikulums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prodi_id')->constrained('program_studis')->restrictOnDelete();
            $table->string('nama', 100);
            $table->foreignId('tahun_akademik_id')->nullable()->constrained('tahun_akademik')->nullOnDelete();
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['prodi_id', 'nama']);
        });

        Schema::create('kurikulum_mata_kuliah', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kurikulum_id')->constrained('kurikulums')->cascadeOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliahs')->cascadeOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->string('jenis', 10)->default('Wajib');
            $table->timestamps();

            $table->unique(['kurikulum_id', 'mata_kuliah_id']);
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('kurikulum_mata_kuliah');
        Schema::dropIfExists('kurikulums');
        PermissionCatalog::sync();
    }
};
