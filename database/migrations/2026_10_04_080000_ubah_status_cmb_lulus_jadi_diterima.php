<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hasil seleksi PMB memakai istilah alur Yapika: Diterima / Ditolak (sebelumnya Lulus / Ditolak).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cmb')->where('status_pendaftaran', 'lulus')->update(['status_pendaftaran' => 'diterima']);
    }

    public function down(): void
    {
        DB::table('cmb')->where('status_pendaftaran', 'diterima')->update(['status_pendaftaran' => 'lulus']);
    }
};
