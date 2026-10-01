<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kecamatan berkode Feeder PDDIKTI (6 digit), sama dengan pilihan di pmb.stikesyapika.ac.id.
        Schema::create('wilayah_kecamatan', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 6)->unique();
            $table->string('nama');
        });

        $kecamatan = json_decode(file_get_contents(database_path('data/wilayah_kecamatan.json')), true);
        foreach (array_chunk($kecamatan, 1000) as $potongan) {
            DB::table('wilayah_kecamatan')->insert($potongan);
        }

        // Kode agama Feeder; master Agama yang sudah berisi nama yang sama tidak ditimpa.
        $sekarang = now();
        DB::table('agamas')->insertOrIgnore(collect([
            '1' => 'Islam', '2' => 'Kristen', '3' => 'Katolik', '4' => 'Hindu', '5' => 'Buddha', '6' => 'Konghucu', '99' => 'Lainnya',
        ])->map(fn (string $nama, string $kode): array => ['kode' => $kode, 'nama' => $nama, 'created_at' => $sekarang, 'updated_at' => $sekarang])->values()->all());

        // Pendaftar PMB (calon mahasiswa baru); isian mengikuti formulir pmb.stikesyapika.ac.id.
        Schema::create('cmb', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pengaturan_pmb_id')->constrained('pengaturan_pmb')->restrictOnDelete();
            $table->string('nomor_pendaftaran', 40)->unique();

            $table->string('nama');
            $table->string('tempat_lahir', 100);
            $table->date('tanggal_lahir');
            $table->string('nama_ibu');
            $table->foreignId('agama_id')->constrained('agamas')->restrictOnDelete();
            $table->char('jenis_kelamin', 1);
            $table->char('status_sipil', 1)->nullable();

            $table->char('nik', 16);
            $table->string('kewarganegaraan', 2)->default('ID');
            $table->string('npwp', 16)->nullable();
            $table->string('jalan');
            $table->string('dusun', 100);
            $table->string('rt', 3);
            $table->string('rw', 3);
            $table->string('kelurahan', 100);
            $table->foreignId('wilayah_kecamatan_id')->constrained('wilayah_kecamatan')->restrictOnDelete();
            $table->string('kode_pos', 5);
            $table->unsignedTinyInteger('alat_transportasi')->nullable();
            $table->unsignedTinyInteger('jenis_tinggal')->nullable();
            $table->unsignedTinyInteger('jenis_masuk')->nullable();
            $table->string('email');
            $table->string('telepon_wali', 20)->nullable();
            $table->string('hp', 20);
            $table->boolean('penerima_kps')->default(false);
            $table->string('nomor_kps', 30)->nullable();
            $table->unsignedTinyInteger('jenis_pembiayaan')->nullable();
            $table->unsignedBigInteger('jumlah_pembiayaan')->nullable();

            $table->char('kelas', 1);
            $table->foreignId('program_studi_id')->constrained('program_studis')->restrictOnDelete();
            $table->char('status_masuk', 1);
            $table->string('asal_sekolah')->nullable();
            $table->string('nisn', 10)->nullable();
            $table->string('nilai_un', 10)->nullable();
            $table->string('asal_perguruan_tinggi')->nullable();
            $table->char('jenjang_asal', 1)->nullable();
            $table->string('prodi_asal')->nullable();
            $table->string('nim_asal', 30)->nullable();
            $table->unsignedSmallInteger('sks_diakui')->nullable();
            $table->string('agen')->nullable();
            $table->string('info')->nullable();

            // Diisi panitia sesudah seleksi; status kosong = belum diputuskan.
            $table->decimal('nilai', 5, 2)->nullable();
            $table->string('status_pendaftaran', 10)->nullable();
            $table->timestamps();

            $table->unique(['pengaturan_pmb_id', 'nik']);
            $table->index(['pengaturan_pmb_id', 'status_pendaftaran']);
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('cmb');
        Schema::dropIfExists('wilayah_kecamatan');
        PermissionCatalog::sync();
    }
};
