<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zona waktu aplikasi berpindah dari UTC ke config('app.timezone') (Asia/Jakarta).
 *
 * Timestamp yang ditulis sistem (created_at, started_at, dll.) selama ini disimpan sebagai jam UTC,
 * jadi digeser sebesar offset zona waktu agar tetap menunjuk momen yang sama. Tenggat yang diketik
 * pengguna (tenggat_waktu) sejak awal sudah berupa jam WIB, sehingga tidak ikut digeser.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $systemColumns = [
        'users' => ['email_verified_at'],
        'failed_jobs' => ['failed_at'],
        'quiz_attempts' => ['started_at', 'submitted_at'],
        'pengumpulan_tugas' => ['submitted_at'],
        'pengajuan_pindah_kelas' => ['diproses_at'],
    ];

    public function up(): void
    {
        $this->shift(1);
    }

    public function down(): void
    {
        $this->shift(-1);
    }

    private function shift(int $direction): void
    {
        $minutes = Carbon::now(config('app.timezone'))->utcOffset() * $direction;

        if ($minutes === 0) {
            return;
        }

        foreach ($this->columns() as $table => $columns) {
            $assignments = collect($columns)
                ->map(fn (string $column): string => DB::getDriverName() === 'sqlite'
                    ? "{$column} = datetime({$column}, '{$minutes} minutes')"
                    : "{$column} = DATE_ADD({$column}, INTERVAL {$minutes} MINUTE)")
                ->implode(', ');

            DB::statement("UPDATE {$table} SET {$assignments}");
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function columns(): array
    {
        $columns = [];

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            $names = Schema::getColumnListing($table);
            $selected = array_values(array_intersect(
                array_merge(['created_at', 'updated_at'], $this->systemColumns[$table] ?? []),
                $names,
            ));

            if ($selected !== []) {
                $columns[$table] = $selected;
            }
        }

        return $columns;
    }
};
