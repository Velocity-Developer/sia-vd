<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\SyaratUjian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Jadwal ujian (UTS/UAS) satu kelas. Ditentukan admin, termasuk modenya; pertemuan UTS/UAS kelas
 * mengikuti tanggal, jam, dan ruang dari jadwal ini.
 */
class Ujian extends Model
{
    use SerializesDatesInAppTimezone;

    public const JENIS = [Pertemuan::UTS, Pertemuan::UAS];

    /** Remidi mata kuliah: satu per kelas, tanpa pertemuan, hanya untuk peserta remidi yang lunas. */
    public const REMIDI = 'remidi';

    public const SEMUA_JENIS = [Pertemuan::UTS, Pertemuan::UAS, self::REMIDI];

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
     * Lembar soal (mode soal di sistem).
     */
    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    /**
     * Sudah ada mahasiswa yang mengumpulkan jawaban atau mengerjakan soal.
     */
    public function sudahDikerjakan(): bool
    {
        return $this->jawabans()->exists() || QuizAttempt::query()->whereHas('quiz', fn ($q) => $q->where('ujian_id', $this->id))->exists();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeTerbit(Builder $query): void
    {
        $query->where('status', self::TERBIT);
    }

    public function remidi(): bool
    {
        return $this->jenis === self::REMIDI;
    }

    public function labelJenis(): string
    {
        return $this->remidi() ? 'Remidi' : strtoupper($this->jenis);
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
     * dispensasi, atau syarat belum diberlakukan. Ujian remidi: hanya peserta remidi yang lunas.
     */
    public function bolehIkut(int $mahasiswaId): bool
    {
        if ($this->remidi()) {
            return RemidiPeserta::query()->where('kelas_id', $this->kelas_id)->where('mahasiswa_id', $mahasiswaId)->lunas()->exists();
        }

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

        // Baris milik mahasiswa ini dibuat dulu. Tanpa ini, bila beberapa mahasiswa mulai bersamaan, penandaan
        // hadir bisa mendahului pengisian peserta oleh request lain lalu tertimpa baris Alpa.
        PresensiMahasiswa::query()->insertOrIgnore([
            'pertemuan_id' => $pertemuan->id,
            'mahasiswa_id' => $mahasiswaId,
            'status' => PresensiMahasiswa::ALPA,
            'metode' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
        if ($this->remidi()) {
            return null;
        }

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
     * Nilai 0–100 tiap mahasiswa yang sudah dinilai: dari lembar soal (skor dikonversi) atau dari nilai dosen.
     *
     * @return Collection<int, float>
     */
    public function nilaiPeserta(): Collection
    {
        if ($this->mode === self::ONLINE_SOAL) {
            $quiz = $this->quiz()->withSum('questions', 'points')->first();

            return $quiz === null ? collect() : $quiz->attempts()->whereNotNull('submitted_at')->get(['mahasiswa_id', 'score'])
                ->mapWithKeys(fn (QuizAttempt $a): array => [$a->mahasiswa_id => self::nilaiDariSkor($a->score, (int) $quiz->questions_sum_points)])
                ->filter(fn (?float $n): bool => $n !== null);
        }

        return $this->jawabans()->whereNotNull('nilai')->pluck('nilai', 'mahasiswa_id')->map(fn ($n): float => (float) $n);
    }

    /**
     * Skor lembar soal (poin) dikonversi ke skala 0–100, agar sama dengan nilai mode lain.
     */
    public static function nilaiDariSkor(int|float|string|null $skor, int $totalPoin): ?float
    {
        if ($skor === null || $totalPoin <= 0) {
            return null;
        }

        return round(min(100, (float) $skor / $totalPoin * 100), 2);
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
