<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TahunAkademik extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'tahun_akademik';

    protected $fillable = ['tahun', 'semester', 'tanggal_mulai', 'tanggal_akhir', 'tanggal_krs_awal', 'tanggal_krs_akhir', 'status'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_akhir' => 'date',
            'status' => 'boolean',
        ];
    }

    public function kelasKuliahs(): HasMany
    {
        return $this->hasMany(KelasKuliah::class, 'tahun_akademik_id');
    }

    /**
     * Periode pengisian KRS sedang berjalan. Tanggal kosong berarti periode dianggap tertutup.
     */
    public function periodeKrsAktif(): bool
    {
        if ($this->tanggal_krs_awal === null || $this->tanggal_krs_akhir === null) {
            return false;
        }

        return Carbon::today()->between(
            Carbon::parse($this->tanggal_krs_awal)->startOfDay(),
            Carbon::parse($this->tanggal_krs_akhir)->endOfDay(),
        );
    }
}
