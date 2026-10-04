<?php

namespace App\Models;

use App\Feature;
use App\Models\Concerns\DibatasiProdi;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\SyaratUjian;
use App\UjianSusulan;
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
    use DibatasiProdi, SerializesDatesInAppTimezone;

    public const JENIS = [Pertemuan::UTS, Pertemuan::UAS];

    /** Remidi mata kuliah: satu per kelas, tanpa pertemuan, hanya untuk peserta remidi yang lunas. */
    public const REMIDI = 'remidi';

    /** Ujian susulan UTS/UAS: satu per kelas per jenis, hanya untuk pemohon susulan yang lunas. */
    public const UTS_SUSULAN = 'uts_susulan';

    public const UAS_SUSULAN = 'uas_susulan';

    public const JENIS_SUSULAN = [self::UTS_SUSULAN, self::UAS_SUSULAN];

    public const SEMUA_JENIS = [Pertemuan::UTS, Pertemuan::UAS, self::REMIDI, self::UTS_SUSULAN, self::UAS_SUSULAN];

    /**
     * Jenis ujian susulan untuk jenis ujian utama (uts -> uts_susulan).
     */
    public static function jenisSusulanUntuk(string $jenisUtama): string
    {
        return $jenisUtama.'_susulan';
    }

    public const TATAP_MUKA = 'tatap_muka';

    public const ONLINE_BERKAS = 'online_berkas';

    public const ONLINE_SOAL = 'online_soal';

    public const MODE = [self::TATAP_MUKA, self::ONLINE_BERKAS, self::ONLINE_SOAL];

    /**
     * Mode yang boleh dipilih untuk jadwal baru atau yang diubah: tanpa fitur ujian_online hanya tatap muka.
     *
     * @return list<string>
     */
    public static function modeTersedia(): array
    {
        return Feature::aktif('ujian_online') ? self::MODE : [self::TATAP_MUKA];
    }

    /**
     * Jenis yang boleh dijadwalkan: tanpa fitur ujian_susulan, jenis susulan tidak tersedia.
     *
     * @return list<string>
     */
    public static function jenisTersedia(): array
    {
        return Feature::aktif('ujian_susulan') ? self::SEMUA_JENIS : array_values(array_diff(self::SEMUA_JENIS, self::JENIS_SUSULAN));
    }

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
    public function pengajuanSusulan(): HasMany
    {
        return $this->hasMany(PengajuanSusulan::class);
    }

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

    /**
     * Remidi dan susulan hanya terlihat oleh pesertanya sendiri; ujian utama terlihat seluruh kelas.
     */
    public function khusus(): bool
    {
        return $this->remidi() || $this->susulan();
    }

    /**
     * Mahasiswa termasuk peserta remidi/susulan ini (tanpa memeriksa syarat kehadiran).
     */
    public function termasukPesertaKhusus(int $mahasiswaId): bool
    {
        return match (true) {
            $this->remidi() => RemidiPeserta::query()->where('kelas_id', $this->kelas_id)->where('mahasiswa_id', $mahasiswaId)->lunas()->exists(),
            $this->susulan() => UjianSusulan::pesertaSusulan($this)->contains($mahasiswaId),
            default => true,
        };
    }

    public function susulan(): bool
    {
        return in_array($this->jenis, self::JENIS_SUSULAN, true);
    }

    /**
     * Jenis ujian utama: uts/uas untuk ujian susulannya, jenis sendiri untuk yang lain.
     */
    public function jenisUtama(): string
    {
        return $this->susulan() ? str_replace('_susulan', '', $this->jenis) : $this->jenis;
    }

    /**
     * Ujian utama (UTS/UAS terbit) yang disusul oleh ujian susulan ini.
     */
    public function ujianUtama(): ?self
    {
        return $this->susulan() ? $this->kelasKuliah->ujianTerbit($this->jenisUtama()) : null;
    }

    public function labelJenis(): string
    {
        return match (true) {
            $this->remidi() => 'Remidi',
            $this->susulan() => strtoupper($this->jenisUtama()).' Susulan',
            default => strtoupper($this->jenis),
        };
    }

    public function online(): bool
    {
        return $this->mode !== self::TATAP_MUKA;
    }

    /**
     * UTS/UAS (termasuk susulannya) tatap muka tidak dinilai di halaman ujian: nilainya diisi sebagai komponen nilai
     * di tabel Nilai Mahasiswa / Nilai Semester, agar tidak diinput dua kali. Ujian remidi tetap dinilai di sini.
     */
    public function nilaiLewatKomponen(): bool
    {
        return ! $this->online() && ! $this->remidi();
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
     * Mahasiswa yang boleh mengikuti ujian ini (lihat alasanTidakBolehIkut).
     */
    public function bolehIkut(int $mahasiswaId): bool
    {
        return $this->alasanTidakBolehIkut($mahasiswaId) === null;
    }

    /**
     * Alasan mahasiswa tidak boleh mengikuti ujian ini, atau null bila boleh.
     * - Remidi: hanya peserta remidi yang lunas.
     * - Susulan: hanya pemohon susulan yang disetujui, lunas, dan tidak ikut ujian utama.
     * - UTS/UAS: peserta KRS yang tidak terdaftar susulan untuk ujian ini.
     * Susulan dan UTS/UAS juga memakai syarat kehadiran (bila diberlakukan), kecuali ada dispensasi.
     */
    public function alasanTidakBolehIkut(int $mahasiswaId): ?string
    {
        if ($this->remidi()) {
            return RemidiPeserta::query()->where('kelas_id', $this->kelas_id)->where('mahasiswa_id', $mahasiswaId)->lunas()->exists()
                ? null
                : (Feature::aktif('keuangan') ? 'Anda bukan peserta remidi yang tagihannya lunas.' : 'Anda bukan peserta remidi kelas ini.');
        }

        if ($this->susulan() && ! UjianSusulan::pesertaSusulan($this)->contains($mahasiswaId)) {
            return Feature::aktif('keuangan') ? 'Anda bukan peserta ujian susulan yang tagihannya lunas.' : 'Anda bukan peserta ujian susulan ini.';
        }

        $kelas = $this->kelasKuliah;

        if (! $kelas->krs()->where('mahasiswa_id', $mahasiswaId)->exists()) {
            return 'Anda bukan peserta kelas ini.';
        }

        // Pemohon susulan yang sudah disetujui mengikuti jadwal susulan, bukan ujian utama.
        if (! $this->susulan() && $this->pengajuanSusulan()->where('mahasiswa_id', $mahasiswaId)->where('status', PengajuanSusulan::DISETUJUI)->exists()) {
            return 'Anda terdaftar ujian susulan untuk ujian ini. Ikuti jadwal ujian susulannya.';
        }

        $memenuhi = SyaratUjian::untukKelas($kelas, [$mahasiswaId])['peserta'][$mahasiswaId][$this->jenisUtama()]['memenuhi'] ?? null;

        return $memenuhi === false ? 'Anda belum memenuhi syarat kehadiran untuk mengikuti ujian ini.' : null;
    }

    /**
     * Catat mahasiswa hadir di pertemuan UTS/UAS kelas saat ia mengerjakan ujian online (tidak ada
     * presensi QR untuk ujian online). Pertemuan yang belum dibuka otomatis menjadi berlangsung.
     */
    public function catatHadir(int $mahasiswaId): void
    {
        $pertemuan = $this->pertemuan();

        if ($pertemuan === null) {
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
        // Remidi dan susulan tidak punya pertemuan dan tidak mengubah presensi.
        if ($this->remidi() || $this->susulan()) {
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

    /**
     * @param  Builder<self>  $query
     */
    public static function saringProdi(Builder $query, int $prodiId): void
    {
        $query->whereHas('kelasKuliah');
    }

    public function milikProdi(int $prodiId): bool
    {
        return KelasKuliah::query()->whereKey($this->kelas_id)->exists();
    }
}
