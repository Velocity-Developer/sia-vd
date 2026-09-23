<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dosen_profiles', function (Blueprint $table): void {
            $table->foreignId('prodi_id')->nullable()->after('status_kepegawaian')->constrained('program_studis')->nullOnDelete();
        });

        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->foreignId('prodi_id')->nullable()->after('dosen_wali_id')->constrained('program_studis')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dosen_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prodi_id');
        });

        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prodi_id');
        });
    }
};
