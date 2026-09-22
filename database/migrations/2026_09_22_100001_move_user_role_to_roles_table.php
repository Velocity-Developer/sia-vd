<?php

use App\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pindahkan kolom users.role (string) ke relasi users.role_id -> roles.id.
     */
    public function up(): void
    {
        PermissionCatalog::sync();

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('email')->constrained('roles')->restrictOnDelete();
        });

        $roleIds = DB::table('roles')->where('is_system', true)->pluck('id', 'slug');

        foreach ($roleIds as $slug => $roleId) {
            DB::table('users')->where('role', $slug)->update(['role_id' => $roleId]);
        }

        // Nilai role lama yang tidak dikenal mengikuti default kolom lama (mahasiswa).
        DB::table('users')->whereNull('role_id')->update(['role_id' => $roleIds['mahasiswa']]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('mahasiswa')->after('email');
        });

        DB::table('roles')->get(['id', 'user_type'])->each(
            fn (object $role) => DB::table('users')->where('role_id', $role->id)->update(['role' => $role->user_type])
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
