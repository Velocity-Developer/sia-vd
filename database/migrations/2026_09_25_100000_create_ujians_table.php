<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jadwal ujian (UTS/UAS) per kelas, ditentukan admin. Pertemuan UTS/UAS kelas mengikuti jadwal ini.
        Schema::create('ujians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas_kuliah')->restrictOnDelete();
            $table->string('jenis', 5);
            // tatap_muka | online_berkas | online_soal
            $table->string('mode', 15)->default('tatap_muka');
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_akhir');
            $table->foreignId('ruang_id')->nullable()->constrained('ruangs')->nullOnDelete();
            $table->string('pengawas', 255)->nullable();
            $table->text('petunjuk')->nullable();
            // draf | terbit — mahasiswa dan kartu ujian hanya memakai jadwal yang sudah terbit.
            $table->string('status', 10)->default('draf');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kelas_id', 'jenis']);
            $table->index(['tanggal', 'status']);
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('ujians');
    }
};
