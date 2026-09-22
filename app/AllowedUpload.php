<?php

namespace App;

/**
 * Ekstensi berkas yang boleh diunggah untuk materi, tugas, dan info kuliah.
 *
 * Berkas disimpan di disk public dan disajikan langsung oleh web server, jadi ekstensi yang
 * bisa dieksekusi atau dirender browser (php, html, svg, js, dll.) tidak boleh masuk daftar ini.
 */
class AllowedUpload
{
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
}
