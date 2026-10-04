<?php

namespace App\Models\Concerns;

use App\LingkupProdi;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model yang datanya milik satu program studi. Untuk akun Prodi (LingkupProdi::id() terisi) semua kueri model ini
 * disaring ke prodinya, dan menyimpan data di luar prodinya ditolak (403).
 */
trait DibatasiProdi
{
    /**
     * Saring kueri ke data milik prodi tersebut.
     *
     * @param  Builder<static>  $query
     */
    abstract public static function saringProdi(Builder $query, int $prodiId): void;

    /**
     * Apakah data ini (yang akan disimpan) termasuk prodi tersebut.
     */
    abstract public function milikProdi(int $prodiId): bool;

    public static function bootDibatasiProdi(): void
    {
        static::addGlobalScope('lingkup-prodi', function (Builder $query): void {
            if (($prodiId = LingkupProdi::id()) !== null) {
                static::saringProdi($query, $prodiId);
            }
        });

        static::saving(function (self $model): void {
            if (($prodiId = LingkupProdi::id()) !== null && ! $model->milikProdi($prodiId)) {
                abort(403, 'Data ini di luar program studi Anda.');
            }
        });
    }
}
