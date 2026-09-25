<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\SyaratUjian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    protected $fillable = ['kelas_id', 'jenis', 'mode', 'tanggal', 'jam_mulai', 'jam_akhir', 'ruang_id', 'pengawas', 'petunjuk', 'status', 'dibuat_oleh', 'soal_berkas', 'nilai_dirilis'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'soal_berkas' => 'array',
            'nilai_dirilis' => 'boolean',
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

    public function jawabans(): HasMany
    {
        return $this->hasMany(UjianJawaban::class);
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

    public function sudahMulai(): bool
    {
        return now()->gte($this->mulaiAt());
    }

    public function sudahSelesai(): bool
    {
        return now()->gt($this->akhirAt());
    }

    public function sedangBerlangsung(): bool
    {
        return $this->sudahMulai() && ! $this->sudahSelesai();
    }

    /**
     * Mahasiswa peserta kelas yang boleh mengikuti ujian ini: syarat kehadiran dipenuhi, mendapat
     * dispensasi, atau syarat belum diberlakukan.
     */
    public function bolehIkut(int $mahasiswaId): bool
    {
        $kelas = $this->kelasKuliah;

        if (! $kelas->krs()->where('mahasiswa_id', $mahasiswaId)->exists()) {
            return false;
        }

        return (SyaratUjian::untukKelas($kelas, [$mahasiswaId])['peserta'][$mahasiswaId][$this->jenis]['memenuhi'] ?? null) !== false;
    }

    /**
     * Catat mahasiswa hadir di pertemuan UTS/UAS kelas saat ia mengerjakan ujian online (tidak ada
     * presensi QR untuk ujian online). Pertemuan yang belum dibuka otomatis menjadi berlangsung.
     */
    public function catatHadir(int $mahasiswaId): void
    {
        $pertemuan = $this->pertemuan();

        if ($pertemuan === null || $pertemuan->status === Pertemuan::DIBATALKAN) {
            return;
        }

        if ($pertemuan->status === Pertemuan::DIJADWALKAN) {
            $pertemuan->update(['status' => Pertemuan::BERLANGSUNG]);
            $pertemuan->siapkanPeserta();
        }

        PresensiMahasiswa::query()
            ->where('pertemuan_id', $pertemuan->id)
            ->where('mahasiswa_id', $mahasiswaId)
            ->whereNotIn('status', PresensiMahasiswa::DIHITUNG_HADIR)
            ->update(['status' => PresensiMahasiswa::HADIR, 'metode' => 'ujian', 'waktu_presensi' => now()]);
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
