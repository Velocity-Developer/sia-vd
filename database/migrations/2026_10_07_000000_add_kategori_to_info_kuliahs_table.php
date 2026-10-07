<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Label kecil (mis. "Beasiswa", "Akademik") yang tampil di daftar Informasi & Pengumuman halaman depan.
     */
    public function up(): void
    {
        Schema::table('info_kuliahs', function (Blueprint $table) {
            $table->string('kategori', 50)->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('info_kuliahs', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
};
