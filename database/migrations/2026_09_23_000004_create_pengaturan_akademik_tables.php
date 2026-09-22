<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skala nilai dan batas SKS yang bisa diatur admin (sebelumnya ditulis langsung di kode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skala_nilais', function (Blueprint $table): void {
            $table->id();
            $table->string('huruf', 2)->unique();
            $table->decimal('bobot', 3, 2);
            $table->boolean('lulus')->default(true);
            $table->boolean('boleh_diulang')->default(false);
            $table->timestamps();
        });

        Schema::create('batas_sks', function (Blueprint $table): void {
            $table->id();
            $table->decimal('ips_minimal', 3, 2)->unique();
            $table->unsignedTinyInteger('maks_sks');
            $table->timestamps();
        });

        Schema::create('pengaturan_akademik', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedTinyInteger('maks_sks_tanpa_ips');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        DB::table('skala_nilais')->insert(array_map(fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now], [
            ['huruf' => 'A', 'bobot' => 4, 'lulus' => true, 'boleh_diulang' => false],
            ['huruf' => 'B', 'bobot' => 3, 'lulus' => true, 'boleh_diulang' => false],
            ['huruf' => 'C', 'bobot' => 2, 'lulus' => true, 'boleh_diulang' => false],
            ['huruf' => 'D', 'bobot' => 1, 'lulus' => true, 'boleh_diulang' => true],
            ['huruf' => 'E', 'bobot' => 0, 'lulus' => false, 'boleh_diulang' => true],
        ]));

        DB::table('batas_sks')->insert(array_map(fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now], [
            ['ips_minimal' => 3.00, 'maks_sks' => 24],
            ['ips_minimal' => 2.50, 'maks_sks' => 21],
            ['ips_minimal' => 2.00, 'maks_sks' => 18],
            ['ips_minimal' => 0.00, 'maks_sks' => 15],
        ]));

        DB::table('pengaturan_akademik')->insert(['id' => 1, 'maks_sks_tanpa_ips' => 20, 'created_at' => $now, 'updated_at' => $now]);

        // Daftarkan permission admin.pengaturan-akademik dan berikan ke role Admin.
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_akademik');
        Schema::dropIfExists('batas_sks');
        Schema::dropIfExists('skala_nilais');
    }
};
