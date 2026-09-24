<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pertemuans', function (Blueprint $table) {
            // Jadwal mingguan asal pertemuan, agar perubahan jadwal bisa diteruskan ke pertemuan yang belum berjalan.
            $table->foreignId('jadwal_id')->nullable()->after('kelas_id')->constrained('jadwals')->nullOnDelete();
            // true bila tanggal/jam/ruang pernah diubah manual; pertemuan seperti ini tidak ikut disusun ulang.
            $table->boolean('jadwal_manual')->default(false)->after('jadwal_id');
        });

        // Pertemuan yang sudah ada: cocokkan dengan jadwal kelasnya lewat hari + jam mulai; yang tidak cocok
        // dianggap sudah dijadwal ulang manual.
        $hari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $jadwal = DB::table('jadwals')->get(['id', 'kelas_id', 'hari', 'jam_mulai'])->groupBy('kelas_id');

        DB::table('pertemuans')->orderBy('id')->each(function ($pertemuan) use ($hari, $jadwal): void {
            $namaHari = $hari[Carbon::parse($pertemuan->tanggal)->dayOfWeekIso];
            $cocok = ($jadwal[$pertemuan->kelas_id] ?? collect())
                ->first(fn ($j) => $j->hari === $namaHari && substr($j->jam_mulai, 0, 5) === substr($pertemuan->jam_mulai, 0, 5));

            DB::table('pertemuans')->where('id', $pertemuan->id)->update(
                $cocok ? ['jadwal_id' => $cocok->id] : ['jadwal_manual' => true]
            );
        });
    }

    public function down(): void
    {
        Schema::table('pertemuans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jadwal_id');
            $table->dropColumn('jadwal_manual');
        });
    }
};
