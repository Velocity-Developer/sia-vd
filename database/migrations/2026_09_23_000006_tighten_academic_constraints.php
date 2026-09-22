<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom yang selalu dianggap wajib oleh aplikasi dijadikan NOT NULL, dan keunikan disesuaikan:
 * - kode kelas cukup unik per tahun akademik (kode yang sama boleh dipakai lagi di semester lain);
 * - satu tahun + semester hanya boleh ada sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ensureClean();

        Schema::table('kelas_kuliah', function (Blueprint $table): void {
            $table->dropUnique(['kode_kelas']);
            $table->dropForeign(['tahun_akademik_id']);
        });
        Schema::table('kelas_kuliah', function (Blueprint $table): void {
            $table->unsignedBigInteger('tahun_akademik_id')->nullable(false)->change();
            $table->foreign('tahun_akademik_id')->references('id')->on('tahun_akademik')->restrictOnDelete();
            $table->unique(['tahun_akademik_id', 'kode_kelas']);
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['role_id']));
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id')->nullable(false)->change();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });

        Schema::table('mahasiswa_profiles', fn (Blueprint $table) => $table->string('nim')->nullable(false)->change());

        Schema::table('tahun_akademik', fn (Blueprint $table) => $table->unique(['tahun', 'semester']));
    }

    public function down(): void
    {
        Schema::table('tahun_akademik', fn (Blueprint $table) => $table->dropUnique(['tahun', 'semester']));
        Schema::table('mahasiswa_profiles', fn (Blueprint $table) => $table->string('nim')->nullable()->change());

        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['role_id']));
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id')->nullable()->change();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });

        Schema::table('kelas_kuliah', function (Blueprint $table): void {
            $table->dropUnique(['tahun_akademik_id', 'kode_kelas']);
            $table->dropForeign(['tahun_akademik_id']);
        });
        Schema::table('kelas_kuliah', function (Blueprint $table): void {
            $table->unsignedBigInteger('tahun_akademik_id')->nullable()->change();
            $table->foreign('tahun_akademik_id')->references('id')->on('tahun_akademik')->restrictOnDelete();
            $table->unique('kode_kelas');
        });
    }

    /**
     * Hentikan migrasi dengan pesan yang jelas bila data lama belum memenuhi aturan baru.
     */
    private function ensureClean(): void
    {
        $masalah = array_filter([
            'kelas kuliah tanpa tahun akademik' => DB::table('kelas_kuliah')->whereNull('tahun_akademik_id')->count(),
            'user tanpa role' => DB::table('users')->whereNull('role_id')->count(),
            'mahasiswa tanpa NIM' => DB::table('mahasiswa_profiles')->whereNull('nim')->count(),
            'tahun akademik ganda (tahun + semester sama)' => DB::table('tahun_akademik')->select('tahun', 'semester')->groupBy('tahun', 'semester')->havingRaw('COUNT(*) > 1')->get()->count(),
        ]);

        if ($masalah !== []) {
            throw new RuntimeException('Perbaiki data berikut sebelum migrasi: '.collect($masalah)->map(fn (int $jumlah, string $apa): string => "{$jumlah} {$apa}")->implode('; ').'.');
        }
    }
};
