<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Komponen nilai global (Akademik → Penilaian → Tambah Komponen Nilai). Persen semua komponen berjumlah 100;
 * nilai akhir KRS = rata-rata berbobot angka tiap komponen (lihat App\NilaiSemester).
 */
class KomponenNilai extends Model
{
    use SerializesDatesInAppTimezone;

    /** Diisi dosen/admin di tabel nilai. */
    public const MANUAL = 'manual';

    /** Persentase kehadiran mahasiswa di kelas, dihitung dari presensi (lihat App\NilaiSemester::kehadiran()). */
    public const KEHADIRAN = 'kehadiran';

    public const SUMBER = [self::MANUAL, self::KEHADIRAN];

    protected $fillable = ['nama', 'persen', 'sumber', 'urutan'];

    protected function casts(): array
    {
        return ['persen' => 'float', 'urutan' => 'integer'];
    }

    /**
     * @return Collection<int, self>
     */
    public static function urut(): Collection
    {
        return static::query()->orderBy('urutan')->orderBy('id')->get();
    }

    public function otomatis(): bool
    {
        return $this->sumber === self::KEHADIRAN;
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(NilaiKomponen::class);
    }
}
