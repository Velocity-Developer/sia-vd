<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jadwal ujian (UTS/UAS) satu kelas. Ditentukan admin, termasuk modenya; pertemuan UTS/UAS kelas
 * mengikuti tanggal, jam, dan ruang dari jadwal ini.
 */
class Ujian extends Model
{
    use SerializesDatesInAppTimezone;

    public const JENIS = [Pertemuan::UTS, Pertemuan::UAS];

    public const TATAP_MUKA = 'tatap_muka';

    public const ONLINE_BERKAS = 'online_berkas';

    public const ONLINE_SOAL = 'online_soal';

    public const MODE = [self::TATAP_MUKA, self::ONLINE_BERKAS, self::ONLINE_SOAL];

    public const DRAF = 'draf';

    public const TERBIT = 'terbit';

    protected $fillable = ['kelas_id', 'jenis', 'mode', 'tanggal', 'jam_mulai', 'jam_akhir', 'ruang_id', 'pengawas', 'petunjuk', 'status', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
        ];
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeTerbit(Builder $query): void
    {
        $query->where('status', self::TERBIT);
    }

    public function online(): bool
    {
        return $this->mode !== self::TATAP_MUKA;
    }

    public function mulaiAt(): Carbon
    {
        return Carbon::parse($this->tanggal->toDateString().' '.$this->jam_mulai);
    }

    public function akhirAt(): Carbon
    {
        return Carbon::parse($this->tanggal->toDateString().' '.$this->jam_akhir);
    }

    /**
     * Pertemuan UTS/UAS kelas yang dipasangkan dengan ujian ini (jenis sama).
     */
    public function pertemuan(): ?Pertemuan
    {
        return Pertemuan::query()->where('kelas_id', $this->kelas_id)->where('jenis', $this->jenis)->first();
    }

    /**
     * Samakan tanggal, jam, dan ruang pertemuan UTS/UAS dengan jadwal ujian. Pertemuan yang sudah
     * berjalan tidak diubah. Ujian online tidak memakai ruang.
     *
     * @return bool false bila pertemuan tidak ada atau sudah berjalan
     */
    public function sinkronkanPertemuan(): bool
    {
        $pertemuan = $this->pertemuan();

        if ($pertemuan === null || $pertemuan->status !== Pertemuan::DIJADWALKAN) {
            return false;
        }

        $pertemuan->update([
            'tanggal' => $this->tanggal,
            'jam_mulai' => $this->jam_mulai,
            'jam_akhir' => $this->jam_akhir,
            'ruang_id' => $this->online() ? null : $this->ruang_id,
            // Jadwal ujian yang mengatur; pertemuan ini tidak ikut disusun ulang dari jadwal mingguan.
            'jadwal_manual' => true,
        ]);

        return true;
    }

    /**
     * Label singkat untuk tampilan dan PDF.
     */
    public function labelMode(): string
    {
        return match ($this->mode) {
            self::ONLINE_BERKAS => 'Online (unggah berkas)',
            self::ONLINE_SOAL => 'Online (soal di sistem)',
            default => 'Tatap muka',
        };
    }
}
