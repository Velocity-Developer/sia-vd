<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bobot nilai per prodi: kolomnya sama dengan skala_nilais, satu set huruf untuk tiap prodi.
        Schema::create('bobot_nilais', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prodi_id')->constrained('program_studis')->cascadeOnDelete();
            $table->string('huruf', 2);
            $table->decimal('bobot', 3, 2);
            $table->decimal('angka_minimal', 5, 2)->nullable();
            $table->boolean('lulus')->default(true);
            $table->boolean('boleh_diulang')->default(false);
            $table->timestamps();

            $table->unique(['prodi_id', 'huruf']);
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('bobot_nilais');
        PermissionCatalog::sync();
    }
};
