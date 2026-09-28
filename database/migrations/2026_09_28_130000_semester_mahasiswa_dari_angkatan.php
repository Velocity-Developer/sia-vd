<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Semester mahasiswa tidak lagi diketik admin, tetapi dihitung dari angkatan dan tahun akademik
 * (`MahasiswaProfile::semesterPada`). Karena itu angkatan wajib ada, dan tahun akademik harus berformat
 * "2026/2027" dengan semester Ganjil/Genap.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tanpaAngkatan = DB::table('mahasiswa_profiles')->whereNull('angkatan')->count();
        if ($tanpaAngkatan > 0) {
            throw new RuntimeException("Masih ada {$tanpaAngkatan} mahasiswa tanpa angkatan. Isi angkatannya dulu sebelum migrasi.");
        }

        $tahunTidakSesuai = DB::table('tahun_akademik')->get(['tahun', 'semester'])
            ->reject(function (object $tahun): bool {
                if (preg_match('/^(\d{4})\/(\d{4})$/', $tahun->tahun, $cocok) !== 1) {
                    return false;
                }

                return (int) $cocok[2] === (int) $cocok[1] + 1 && in_array($tahun->semester, ['Ganjil', 'Genap'], true);
            })
            ->map(fn (object $tahun): string => "{$tahun->tahun} {$tahun->semester}");
        if ($tahunTidakSesuai->isNotEmpty()) {
            throw new RuntimeException('Tahun akademik berikut belum berformat "2026/2027" dengan semester Ganjil/Genap: '.$tahunTidakSesuai->implode(', ').'. Perbaiki dulu sebelum migrasi.');
        }

        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('angkatan')->nullable(false)->change();
            $table->dropColumn('semester');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('angkatan')->nullable()->change();
            $table->unsignedTinyInteger('semester')->nullable()->after('angkatan');
        });
    }
};
