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
        // Predikat kelulusan per rentang IPK; dibekukan ke wisuda saat SKL terbit (sebelumnya ditulis di Wisuda::predikat()).
        Schema::create('predikats', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 60)->unique();
            $table->decimal('bobot_minimal', 3, 2);
            $table->decimal('bobot_maksimal', 3, 2);
            $table->timestamps();
        });

        $now = now();
        DB::table('predikats')->insert(array_map(fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now], [
            ['nama' => 'Dengan Pujian (Cum Laude)', 'bobot_minimal' => 3.51, 'bobot_maksimal' => 4.00],
            ['nama' => 'Sangat Memuaskan', 'bobot_minimal' => 3.01, 'bobot_maksimal' => 3.50],
            ['nama' => 'Memuaskan', 'bobot_minimal' => 2.76, 'bobot_maksimal' => 3.00],
            ['nama' => 'Cukup', 'bobot_minimal' => 0.00, 'bobot_maksimal' => 2.75],
        ]));

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('predikats');
        PermissionCatalog::sync();
    }
};
