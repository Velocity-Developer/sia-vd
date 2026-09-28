<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status mahasiswa "Transfer Masuk" dihapus. Mahasiswa pindahan diperlakukan seperti mahasiswa Aktif
 * (sebelumnya mereka boleh KRS tetapi tidak pernah ditagih), jadi statusnya diubah menjadi Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('mahasiswa_profiles')->where('status', 'Transfer Masuk')->update(['status' => 'Aktif']);
    }

    public function down(): void
    {
        // Mahasiswa yang dulu berstatus Transfer Masuk tidak bisa dibedakan lagi dari yang Aktif.
    }
};
