<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_studis', function (Blueprint $table): void {
            $table->string('gelar_akademik', 150)->nullable()->after('jenjang');
            $table->string('singkatan_gelar', 30)->nullable()->after('gelar_akademik');
            // Belum dipakai perhitungan apa pun; nanti menimpa batas lulus di Pengaturan Akademik bila diisi.
            $table->unsignedSmallInteger('sks_lulus')->nullable()->after('singkatan_gelar');
            $table->string('status_prodi', 30)->nullable()->after('sks_lulus');
            $table->string('nomor_kaprodi', 30)->nullable()->after('kaprodi');
            $table->string('operator')->nullable()->after('nomor_kaprodi');
            $table->string('nomor_operator', 30)->nullable()->after('operator');
            $table->string('no_sk_dikti', 100)->nullable()->after('nomor_operator');
            $table->date('tanggal_sk_dikti')->nullable()->after('no_sk_dikti');
            $table->date('tanggal_berakhir_sk_dikti')->nullable()->after('tanggal_sk_dikti');
            $table->text('alamat')->nullable()->after('tahun_berdiri');
            $table->foreignId('provinsi_id')->nullable()->after('alamat')->constrained('provinsis')->restrictOnDelete();
            $table->foreignId('kota_id')->nullable()->after('provinsi_id')->constrained('kotas')->restrictOnDelete();
            $table->string('kode_pos', 10)->nullable()->after('kota_id');
            $table->string('telepon', 30)->nullable()->after('kode_pos');
            $table->string('faximili', 30)->nullable()->after('telepon');
            $table->string('email')->nullable()->after('faximili');
            $table->string('website')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('program_studis', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('provinsi_id');
            $table->dropConstrainedForeignId('kota_id');
            $table->dropColumn([
                'gelar_akademik', 'singkatan_gelar', 'sks_lulus', 'status_prodi', 'nomor_kaprodi', 'operator', 'nomor_operator',
                'no_sk_dikti', 'tanggal_sk_dikti', 'tanggal_berakhir_sk_dikti', 'alamat', 'kode_pos', 'telepon', 'faximili',
                'email', 'website',
            ]);
        });
    }
};
