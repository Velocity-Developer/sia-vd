<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan semester dibayar lewat unggah bukti lalu diverifikasi admin, seperti tagihan remidi dan susulan.
 * `rincian_manual` menandai rincian yang diketik admin, agar tidak ditimpa saat tagihan diterbitkan ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_semester', function (Blueprint $table): void {
            $table->boolean('rincian_manual')->default(false)->after('total');
            $table->string('bukti')->nullable()->after('tanggal_lunas');
            $table->timestamp('bukti_diunggah_at')->nullable()->after('bukti');
            $table->string('alasan_tolak')->nullable()->after('bukti_diunggah_at');
            $table->foreignId('diverifikasi_oleh')->nullable()->after('alasan_tolak')->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable()->after('diverifikasi_oleh');
        });

        // Rincian yang diketik admin tidak punya jenis biaya.
        DB::table('tagihan_semester')
            ->whereExists(fn ($query) => $query->from('tagihan_item')->whereColumn('tagihan_item.tagihan_id', 'tagihan_semester.id')->whereNull('tagihan_item.jenis_biaya_id'))
            ->update(['rincian_manual' => true]);

        // Tagihan kosong hasil tombol "Tandai Belum Bayar" sebelum tagihan terbit: tidak pernah ditagihkan
        // tetapi mengunci KRS. Tagihan lunas tanpa rincian dibiarkan karena pembayarannya sudah dicatat.
        DB::table('tagihan_semester')
            ->where('status', 'belum_bayar')
            ->where('total', 0)
            ->whereNotExists(fn ($query) => $query->from('tagihan_item')->whereColumn('tagihan_item.tagihan_id', 'tagihan_semester.id'))
            ->delete();
    }

    public function down(): void
    {
        DB::table('tagihan_semester')->whereIn('status', ['menunggu_verifikasi', 'ditolak'])->update(['status' => 'belum_bayar']);

        Schema::table('tagihan_semester', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('diverifikasi_oleh');
            $table->dropColumn(['rincian_manual', 'bukti', 'bukti_diunggah_at', 'alasan_tolak', 'diverifikasi_at']);
        });
    }
};
