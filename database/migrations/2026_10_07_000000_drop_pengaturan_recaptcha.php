<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Google reCAPTCHA v2 diganti captcha gambar yang selalu aktif di halaman masuk (App\CaptchaGambar),
 * jadi tab pengaturannya, tabelnya, dan izinnya dibuang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pengaturan_recaptcha');

        // Baris permission_role ikut terhapus (cascadeOnDelete).
        DB::table('permissions')->where('key', 'admin.pengaturan-recaptcha')->delete();
    }

    public function down(): void
    {
        // Pengaturan dan izinnya sudah tidak ada di kode, jadi tidak dipulihkan.
    }
};
