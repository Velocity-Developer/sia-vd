<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hentikan hapus berantai yang bisa melenyapkan data akademik (kelas, KRS, nilai).
 *
 * - Master data yang masih dipakai (fakultas, prodi, mata kuliah, ruang, dosen pengampu,
 *   kelas ber-KRS, mahasiswa ber-KRS) tidak bisa dihapus: restrict.
 * - Konten (materi, tugas, quiz, info kuliah) tetap ada meski akun pengunggahnya dihapus:
 *   uploaded_by menjadi null.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, string>> tabel => [kolom => tabel referensi]
     */
    private array $restricted = [
        'program_studis' => ['fakultas_id' => 'fakultas'],
        'mata_kuliahs' => ['prodi_id' => 'program_studis'],
        'kelas_kuliah' => ['dosen_id' => 'dosen_profiles', 'matkul_id' => 'mata_kuliahs'],
        'jadwals' => ['ruang_id' => 'ruangs'],
        'krs' => ['mahasiswa_id' => 'mahasiswa_profiles', 'kelas_id' => 'kelas_kuliah'],
    ];

    /**
     * @var list<string>
     */
    private array $uploadedBy = ['materis', 'tugas', 'quizzes', 'info_kuliahs'];

    public function up(): void
    {
        foreach ($this->restricted as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column => $references) {
                    $blueprint->dropForeign([$column]);
                    $blueprint->foreign($column)->references('id')->on($references)->restrictOnDelete();
                }
            });
        }

        foreach ($this->uploadedBy as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['uploaded_by']);
                $blueprint->foreignId('uploaded_by')->nullable()->change();
                $blueprint->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->restricted as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column => $references) {
                    $blueprint->dropForeign([$column]);
                    $blueprint->foreign($column)->references('id')->on($references)->cascadeOnDelete();
                }
            });
        }

        foreach ($this->uploadedBy as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['uploaded_by']);
                $blueprint->foreignId('uploaded_by')->nullable(false)->change();
                $blueprint->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
