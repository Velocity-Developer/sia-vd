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

    /**
     * Jadwal kelas lain di KRS mahasiswa (tahun akademik yang sama) yang beririsan dengan jadwal kelas ini.
     *
     * @param  list<int>  $kecualiKelasIds  kelas yang diabaikan, mis. kelas asal saat pindah kelas
     */
    public static function bentrokUntukMahasiswa(KelasKuliah $kelasKuliah, int $mahasiswaId, array $kecualiKelasIds = []): ?self
    {
        foreach ($kelasKuliah->jadwals()->get() as $jadwal) {
            $bentrok = static::query()
                ->overlapping($jadwal->hari, $jadwal->jam_mulai, $jadwal->jam_akhir)
                ->inTahunAkademik($kelasKuliah->tahun_akademik_id)
                ->whereIn('kelas_id', Krs::query()->where('mahasiswa_id', $mahasiswaId)->select('kelas_id'))
                ->whereNotIn('kelas_id', [...$kecualiKelasIds, $kelasKuliah->id])
                ->with('kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul')
                ->first();

            if ($bentrok !== null) {
                return $bentrok;
            }
        }

        return null;
    }

    /**
     * Keterangan singkat untuk pesan error, mis. "IF101-A (Algoritma) pada Senin, 08:00–10:00".
     */
    public function keterangan(): string
    {
        return $this->kelasKuliah?->kode_kelas.' ('.$this->kelasKuliah?->mataKuliah?->nama_matkul.') pada '
            .$this->hari.', '.substr($this->jam_mulai, 0, 5).'–'.substr($this->jam_akhir, 0, 5);
    }
}
