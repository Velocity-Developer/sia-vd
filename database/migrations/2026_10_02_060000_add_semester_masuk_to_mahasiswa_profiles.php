<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Semester mahasiswa (mis. pindahan) pada tahun akademik saat semester masuk diisi; semester berikutnya
        // dihitung dari situ, bukan dari angkatan.
        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->unsignedTinyInteger('semester_masuk')->nullable()->after('angkatan');
            $table->foreignId('tahun_akademik_masuk_id')->nullable()->after('semester_masuk')->constrained('tahun_akademik')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tahun_akademik_masuk_id');
            $table->dropColumn('semester_masuk');
        });
    }
};
