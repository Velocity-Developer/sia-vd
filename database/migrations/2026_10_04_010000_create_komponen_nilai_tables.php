<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen nilai global (mis. Kehadiran, Tugas, UTS, UAS) dengan persen bobot berjumlah 100 dan sumbernya (manual atau
 * kehadiran otomatis), angka 0–100 per komponen untuk tiap KRS, dan nilai akhir angka (rata-rata berbobot) di KRS. Huruf tetap di krs.nilai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komponen_nilais', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 60)->unique();
            $table->decimal('persen', 5, 2);
            // manual = diisi dosen/admin; kehadiran = persentase hadir dari presensi kelas (otomatis).
            $table->string('sumber', 20)->default('manual');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('nilai_komponens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('krs_id')->constrained('krs')->cascadeOnDelete();
            $table->foreignId('komponen_nilai_id')->constrained('komponen_nilais')->restrictOnDelete();
            $table->decimal('nilai', 5, 2);
            $table->timestamps();
            $table->unique(['krs_id', 'komponen_nilai_id']);
        });

        Schema::table('krs', function (Blueprint $table): void {
            $table->decimal('nilai_angka', 5, 2)->nullable()->after('nilai');
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('krs', function (Blueprint $table): void {
            $table->dropColumn('nilai_angka');
        });
        Schema::dropIfExists('nilai_komponens');
        Schema::dropIfExists('komponen_nilais');
        PermissionCatalog::sync();
    }
};
