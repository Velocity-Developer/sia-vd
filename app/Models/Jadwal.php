<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jadwal extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'jadwals';

    protected $fillable = ['hari', 'jam_mulai', 'jam_akhir', 'kelas_id', 'ruang_id'];

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_id');
    }

    /**
     * Jadwal pada hari yang sama yang jamnya beririsan dengan rentang jam_mulai–jam_akhir.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, string $hari, string $jamMulai, string $jamAkhir): void
    {
        $query->where('hari', $hari)
            ->where('jam_mulai', '<', $jamAkhir)
            ->where('jam_akhir', '>', $jamMulai);
    }

    /**
     * Jadwal milik kelas kuliah pada tahun akademik tertentu.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInTahunAkademik(Builder $query, ?int $tahunAkademikId): void
    {
        $query->whereHas('kelasKuliah', fn (Builder $kelas) => $kelas->where('tahun_akademik_id', $tahunAkademikId));
    }
}
