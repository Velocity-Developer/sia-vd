<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisBiaya extends Model
{
    use SerializesDatesInAppTimezone;

    /** Nominal tetap per semester. */
    public const TETAP = 'tetap';

    /** Nominal dikali jumlah SKS yang diambil mahasiswa pada semester itu. */
    public const PER_SKS = 'per_sks';

    /** Ikut tagihan semester. */
    public const SEMESTER = 'semester';

    /** Hanya untuk tagihan remidi per mata kuliah. */
    public const REMIDI = 'remidi';

    protected $table = 'jenis_biaya';

    protected $fillable = ['kode', 'nama', 'cara_hitung', 'kategori', 'keterangan', 'aktif', 'urutan'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'urutan' => 'integer'];
    }

    public function tarif(): HasMany
    {
        return $this->hasMany(TarifBiaya::class, 'jenis_biaya_id');
    }

    /**
     * Tarif yang paling cocok untuk mahasiswa: yang paling khusus menang, yaitu
     * prodi+angkatan, lalu prodi saja, lalu angkatan saja, terakhir tarif umum.
     */
    public function tarifUntuk(?int $prodiId, ?int $angkatan): ?TarifBiaya
    {
        return $this->tarif
            ->filter(fn (TarifBiaya $tarif) => ($tarif->prodi_id === null || $tarif->prodi_id === $prodiId)
                && ($tarif->angkatan === null || $tarif->angkatan === $angkatan))
            ->sortByDesc(fn (TarifBiaya $tarif) => ($tarif->prodi_id !== null ? 2 : 0) + ($tarif->angkatan !== null ? 1 : 0))
            ->first();
    }
}
