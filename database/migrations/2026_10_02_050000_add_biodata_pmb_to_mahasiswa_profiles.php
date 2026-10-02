<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Biodata PDDIKTI dari formulir PMB. Mahasiswa hasil salinan calon maba belum punya NIM.
        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->string('nim')->nullable()->change();

            $table->foreignId('cmb_id')->nullable()->unique()->after('user_id')->constrained('cmb')->nullOnDelete();
            $table->char('nik', 16)->nullable()->after('agama');
            $table->string('npwp', 16)->nullable()->after('nik');
            $table->char('status_sipil', 1)->nullable()->after('npwp');
            $table->string('telepon_wali', 20)->nullable()->after('no_telepon');
            $table->string('dusun', 100)->nullable()->after('alamat');
            $table->string('rt', 3)->nullable()->after('dusun');
            $table->string('rw', 3)->nullable()->after('rt');
            $table->string('kelurahan', 100)->nullable()->after('rw');
            $table->foreignId('wilayah_kecamatan_id')->nullable()->after('kelurahan')->constrained('wilayah_kecamatan')->nullOnDelete();
            $table->string('kode_pos', 5)->nullable()->after('wilayah_kecamatan_id');
            $table->unsignedTinyInteger('alat_transportasi')->nullable()->after('kode_pos');
            $table->unsignedTinyInteger('jenis_tinggal')->nullable()->after('alat_transportasi');
            $table->unsignedTinyInteger('jenis_masuk')->nullable()->after('jenis_tinggal');
            $table->boolean('penerima_kps')->default(false)->after('jenis_masuk');
            $table->string('nomor_kps', 30)->nullable()->after('penerima_kps');
            $table->unsignedTinyInteger('jenis_pembiayaan')->nullable()->after('nomor_kps');
            $table->unsignedBigInteger('jumlah_pembiayaan')->nullable()->after('jenis_pembiayaan');

            $table->char('jalur_kelas', 1)->nullable()->after('prodi_id');
            $table->string('nilai_un', 10)->nullable()->after('nisn');
            $table->string('asal_perguruan_tinggi')->nullable()->after('nilai_un');
            $table->char('jenjang_asal', 1)->nullable()->after('asal_perguruan_tinggi');
            $table->string('prodi_asal')->nullable()->after('jenjang_asal');
            $table->string('nim_asal', 30)->nullable()->after('prodi_asal');
            $table->unsignedSmallInteger('sks_diakui')->nullable()->after('nim_asal');
            $table->string('berkas_ijazah')->nullable()->after('foto');
            $table->string('berkas_transkrip')->nullable()->after('berkas_ijazah');
        });

        // Nama izin admin.pendaftar-pmb berganti menjadi "Calon Maba (PMB)".
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cmb_id');
            $table->dropConstrainedForeignId('wilayah_kecamatan_id');
            $table->dropColumn([
                'nik', 'npwp', 'status_sipil', 'telepon_wali', 'dusun', 'rt', 'rw', 'kelurahan', 'kode_pos',
                'alat_transportasi', 'jenis_tinggal', 'jenis_masuk', 'penerima_kps', 'nomor_kps', 'jenis_pembiayaan',
                'jumlah_pembiayaan', 'jalur_kelas', 'nilai_un', 'asal_perguruan_tinggi', 'jenjang_asal', 'prodi_asal',
                'nim_asal', 'sks_diakui', 'berkas_ijazah', 'berkas_transkrip',
            ]);
        });
        // nim dibiarkan nullable: baris tanpa NIM membuat kolom tidak bisa dikembalikan ke NOT NULL.
    }
};
