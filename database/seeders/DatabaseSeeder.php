<?php

namespace Database\Seeders;

use App\PermissionCatalog;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        PermissionCatalog::sync();

        // Data demo menghapus data akademik, jadi tidak pernah dijalankan otomatis di production.
        if (app()->isProduction()) {
            $this->command?->warn('Lingkungan production: hanya permission yang disinkronkan, data demo dilewati.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
