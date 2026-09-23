<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KrsSemester extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'krs_semester';

    protected $fillable = ['mahasiswa_id', 'tahun_akademik_id', 'disimpan_pada'];

    protected function casts(): array
    {
        return ['disimpan_pada' => 'datetime'];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    /**
     * KRS terkunci bila mahasiswa sudah menyimpannya, atau bila periode KRS semester itu berakhir.
     */
    public static function terkunci(int $mahasiswaId, ?TahunAkademik $tahunAkademik): bool
    {
        if ($tahunAkademik === null) {
            return true;
        }

        if (static::tersimpan($mahasiswaId, $tahunAkademik->id)) {
            return true;
        }

        return ! $tahunAkademik->periodeKrsAktif();
    }

    public static function tersimpan(int $mahasiswaId, int $tahunAkademikId): bool
    {
        return static::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->where('tahun_akademik_id', $tahunAkademikId)
            ->exists();
    }
}
