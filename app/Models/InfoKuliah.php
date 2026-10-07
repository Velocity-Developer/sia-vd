<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfoKuliah extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['kategori', 'information', 'file', 'uploaded_by'];

    /**
     * Kolom yang boleh tampil di halaman publik, terbaru dulu. Path berkas tidak ikut; unduhan lewat rute berkas.info-kuliah.
     */
    public static function publik(): Builder
    {
        return self::query()->select(['id', 'kategori', 'information', 'created_at'])->latest()->latest('id');
    }

    /** @return list<string> Kategori yang pernah dipakai, untuk saran isian di form admin. */
    public static function daftarKategori(): array
    {
        return self::query()->whereNotNull('kategori')->distinct()->orderBy('kategori')->pluck('kategori')->all();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
