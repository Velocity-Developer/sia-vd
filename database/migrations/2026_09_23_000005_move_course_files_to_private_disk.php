<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Pindahkan berkas materi, tugas, jawaban tugas, dan info kuliah dari disk public (bisa diunduh siapa pun
 * lewat /storage/...) ke disk privat 'local'. Path di database tidak berubah; unduhan kini lewat BerkasController.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $directories = ['materis', 'tugas', 'pengumpulan-tugas', 'info-kuliahs'];

    public function up(): void
    {
        $this->move('public', 'local');
    }

    public function down(): void
    {
        $this->move('local', 'public');
    }

    private function move(string $from, string $to): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        foreach ($this->directories as $directory) {
            foreach ($source->allFiles($directory) as $path) {
                if (! $target->exists($path)) {
                    $stream = $source->readStream($path);
                    $target->writeStream($path, $stream);

                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                if ($target->exists($path)) {
                    $source->delete($path);
                }
            }
        }
    }
};
