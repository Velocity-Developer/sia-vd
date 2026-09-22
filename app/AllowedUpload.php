<?php

namespace App;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ekstensi berkas yang boleh diunggah untuk materi, tugas, dan info kuliah.
 *
 * Berkas disajikan inline ke browser, jadi ekstensi yang bisa dieksekusi atau dirender
 * browser (php, html, svg, js, dll.) tidak boleh masuk daftar ini.
 */
class AllowedUpload
{
    /**
     * Disk privat untuk berkas materi, tugas, jawaban, dan info kuliah. Berkas hanya bisa diunduh lewat
     * BerkasController yang mengecek hak akses, tidak lewat URL /storage publik.
     */
    public const DISK = 'local';

    public const EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'zip', 'rar', '7z',
        'mp3', 'mp4',
    ];

    public static function rule(): string
    {
        return 'extensions:'.implode(',', self::EXTENSIONS);
    }

    public static function message(): string
    {
        return ':attribute harus berformat '.implode(', ', self::EXTENSIONS).'.';
    }

    /**
     * Salin berkas ke nama baru (untuk duplikasi materi/tugas), agar salinan tidak berbagi berkas fisik
     * dengan aslinya dan menghapus salah satunya tidak menghapus berkas yang lain.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function salinBerkas(array $paths): array
    {
        $disk = Storage::disk(self::DISK);

        return collect($paths)
            ->filter(fn ($path): bool => is_string($path) && $disk->exists($path))
            ->map(function (string $path) use ($disk): string {
                $info = pathinfo($path);
                $nama = preg_replace('/-[a-z0-9]{6}$/', '', $info['filename']);
                $ekstensi = isset($info['extension']) ? '.'.$info['extension'] : '';

                do {
                    $salinan = $info['dirname'].'/'.$nama.'-'.Str::lower(Str::random(6)).$ekstensi;
                } while ($disk->exists($salinan));

                $disk->copy($path, $salinan);

                return $salinan;
            })
            ->values()
            ->all();
    }
}
