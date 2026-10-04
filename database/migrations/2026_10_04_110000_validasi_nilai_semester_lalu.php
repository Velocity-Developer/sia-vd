<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * KHS, transkrip, dan SKL kini hanya memakai nilai yang sudah divalidasi. Nilai semester lalu (tahun akademik tidak
 * aktif) yang terisi sebelum Validasi Nilai ada dianggap sudah sah, agar riwayat hasil studi tidak tiba-tiba kosong.
 * Nilai tahun akademik aktif tetap menunggu validasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kelasLalu = DB::table('kelas_kuliah')
            ->join('tahun_akademik', 'tahun_akademik.id', '=', 'kelas_kuliah.tahun_akademik_id')
            ->where('tahun_akademik.status', false)
            ->pluck('kelas_kuliah.id');

        DB::table('krs')->whereIn('kelas_id', $kelasLalu)->whereNotNull('nilai')->whereNull('nilai_divalidasi_at')
            ->update(['nilai_divalidasi_at' => now()]);
    }

    public function down(): void
    {
        // Tidak dibalik: tidak bisa membedakan validasi otomatis dari validasi admin.
    }
};
