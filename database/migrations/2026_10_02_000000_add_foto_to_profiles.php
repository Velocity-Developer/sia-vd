<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Path foto di disk privat (local); ditampilkan lewat rute berkas.foto yang memeriksa izin.
    private const TABEL = ['admin_profiles', 'dosen_profiles', 'mahasiswa_profiles'];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $table): void {
                $table->string('foto')->nullable()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $table): void {
                $table->dropColumn('foto');
            });
        }
    }
};
