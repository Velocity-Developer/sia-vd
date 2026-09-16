<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pengaturan_pindah_kelas', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->boolean('is_active')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->addSingletonConstraint();

        DB::table('pengaturan_pindah_kelas')->insert([
            'id' => 1,
            'is_active' => false,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_pindah_kelas');
    }

    /**
     * Enforce the singleton row (id = 1) on drivers that support adding constraints.
     */
    private function addSingletonConstraint(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite tidak mendukung ALTER TABLE ADD CONSTRAINT; singleton dijaga oleh model.
            return;
        }

        DB::statement('ALTER TABLE pengaturan_pindah_kelas ADD CONSTRAINT pengaturan_pindah_kelas_singleton_check CHECK (id = 1)');
    }
};
