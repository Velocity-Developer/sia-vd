<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Path berkas unggahan pendaftar di disk privat; hanya bisa diunduh lewat BerkasController.
        Schema::table('cmb', function (Blueprint $table): void {
            $table->string('foto')->nullable()->after('info');
            $table->string('berkas_ijazah')->nullable()->after('foto');
            $table->string('berkas_transkrip')->nullable()->after('berkas_ijazah');
        });
    }

    public function down(): void
    {
        Schema::table('cmb', function (Blueprint $table): void {
            $table->dropColumn(['foto', 'berkas_ijazah', 'berkas_transkrip']);
        });
    }
};
