<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jadwal pendadaran (sidang akhir) yang ditetapkan admin saat menyetujui pendaftaran: ruang dan tiga penguji.
 */
class Pendadaran extends Model
{
    use SerializesDatesInAppTimezone;

    public const DIJADWALKAN = 'dijadwalkan';

    public const SELESAI = 'selesai';

    public const PERAN_PENGUJI = [1 => 'Ketua Penguji', 2 => 'Penguji 2', 3 => 'Penguji 3'];

    protected $table = 'pendadaran';

    protected $fillable = ['pengajuan_id', 'tugas_akhir_id', 'mahasiswa_id', 'tanggal', 'jam_mulai', 'jam_akhir', 'ruang_id', 'penguji_1_id', 'penguji_2_id', 'penguji_3_id', 'status', 'dijadwalkan_oleh'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanAkademik::class, 'pengajuan_id');
    }

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class);
    }

    public function penguji1(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'penguji_1_id');
    }

    public function penguji2(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'penguji_2_id');
    }

    public function penguji3(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'penguji_3_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDiuji(Builder $query, int $dosenId): void
    {
        $query->where(fn (Builder $q) => $q->where('penguji_1_id', $dosenId)->orWhere('penguji_2_id', $dosenId)->orWhere('penguji_3_id', $dosenId));
    }

    /**
     * @return array<int, int> nomor penguji => id dosen
     */
    public function pengujiIds(): array
    {
        return [1 => $this->penguji_1_id, 2 => $this->penguji_2_id, 3 => $this->penguji_3_id];
    }

    /**
     * Peran dosen di pendadaran ini (ketua/penguji), atau null bila bukan penguji.
     */
    public function peranPenguji(int $dosenId): ?string
    {
        $nomor = array_search($dosenId, $this->pengujiIds(), true);

        return $nomor === false ? null : self::PERAN_PENGUJI[$nomor];
    }

    /**
     * @return list<array{peran: string, nama: ?string}>
     */
    public function daftarPenguji(): array
    {
        return [
            ['peran' => self::PERAN_PENGUJI[1], 'nama' => $this->penguji1?->user?->name],
            ['peran' => self::PERAN_PENGUJI[2], 'nama' => $this->penguji2?->user?->name],
            ['peran' => self::PERAN_PENGUJI[3], 'nama' => $this->penguji3?->user?->name],
        ];
    }

    /**
     * Ringkasan jadwal untuk ditampilkan ke mahasiswa/dosen/admin.
     *
     * @return array<string, mixed>
     */
    public function jadwal(): array
    {
        return [
            'id' => $this->id,
            'tanggal' => $this->tanggal->toDateString(),
            'jam_mulai' => substr($this->jam_mulai, 0, 5),
            'jam_akhir' => substr($this->jam_akhir, 0, 5),
            'ruang' => $this->ruang ? trim($this->ruang->kode_ruang.' '.$this->ruang->nama_ruang) : null,
            'penguji' => $this->daftarPenguji(),
            'status' => $this->status,
        ];
    }
}
