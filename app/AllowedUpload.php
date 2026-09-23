<?php

namespace App;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ekstensi berkas yang boleh diunggah untuk materi, tugas, dan info kuliah.
 *
 * Ekstensi yang bisa dieksekusi atau dirender browser (php, html, svg, js, dll.) tidak boleh masuk
 * daftar ini. Saat disajikan, tipe berkas ditentukan dari ekstensi di sini — bukan ditebak dari isinya —
 * dan hanya PDF serta gambar yang dibuka langsung di browser.
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

    /**
     * Tipe berkas per ekstensi yang diizinkan; dipakai saat menyajikan berkas.
     *
     * @var array<string, string>
     */
    public const MIME = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'rtf' => 'application/rtf',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
        '7z' => 'application/x-7z-compressed',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
    ];

    /**
     * Hanya berkas ini yang dibuka langsung di tab browser; selebihnya diunduh.
     *
     * @var list<string>
     */
    public const INLINE = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * extensions: memeriksa nama berkas, mimes: memeriksa isinya. Keduanya wajib cocok agar berkas
     * berisi HTML tidak bisa diselundupkan dengan nama .txt atau .pdf.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['extensions:'.implode(',', self::EXTENSIONS), 'mimes:'.implode(',', self::EXTENSIONS)];
    }

    public static function mime(string $path): string
    {
        return self::MIME[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    public static function bolehInline(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::INLINE, true);
    }

    public static function message(): string
    {
        return ':attribute harus berformat '.implode(', ', self::EXTENSIONS).'.';
    }

    public static function messageIsi(): string
    {
        return 'Isi :attribute tidak sesuai dengan formatnya.';
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
