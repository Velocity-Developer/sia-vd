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
        Schema::create('pengaturan_institusi', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nama_pt');
            $table->string('singkatan')->nullable();
            $table->string('logo')->nullable();
            $table->string('npsn')->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->unsignedSmallInteger('tahun_berdiri')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->addSingletonConstraint();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_institusi');
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

        DB::statement('ALTER TABLE pengaturan_institusi ADD CONSTRAINT pengaturan_institusi_singleton_check CHECK (id = 1)');
    }
};
