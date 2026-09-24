<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Pertemuan extends Model
{
    use SerializesDatesInAppTimezone;

    public const KULIAH = 'kuliah';

    public const UTS = 'uts';

    public const UAS = 'uas';

    public const JENIS = [self::KULIAH, self::UTS, self::UAS];

    public const DIJADWALKAN = 'dijadwalkan';

    public const BERLANGSUNG = 'berlangsung';

    public const SELESAI = 'selesai';

    public const DIBATALKAN = 'dibatalkan';

    public const NAMA_HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    /** Dosen boleh membuka pertemuan sekian menit sebelum jam mulai. */
    public const BUKA_LEBIH_AWAL_MENIT = 15;

    /** Pertemuan yang lupa ditutup dianggap selesai sekian menit sesudah jam akhir. */
    public const TUTUP_OTOMATIS_MENIT = 60;

    /** Kode presensi mandiri berganti setiap sekian detik. */
    public const PERIODE_KODE_DETIK = 30;

    /**
     * Kode dari periode sebelumnya masih diterima, agar mahasiswa yang memindai di detik terakhir
     * (atau harus login dulu) tidak langsung gagal.
     */
    public const PERIODE_KODE_TOLERANSI = 2;

    protected $fillable = [
        'kelas_id', 'pertemuan_ke', 'tanggal', 'jam_mulai', 'jam_akhir', 'ruang_id', 'jenis', 'status',
        'dosen_id', 'dosen_masuk_at', 'dosen_keluar_at', 'topik', 'catatan', 'kode_rahasia', 'mandiri_sampai',
    ];

    protected $hidden = ['kode_rahasia'];

    protected function casts(): array
    {
        return [
            'pertemuan_ke' => 'integer',
            'tanggal' => 'date:Y-m-d',
            'dosen_masuk_at' => 'datetime',
            'dosen_keluar_at' => 'datetime',
            'mandiri_sampai' => 'datetime',
        ];
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'dosen_id');
    }

    public function presensiMahasiswas(): HasMany
    {
        return $this->hasMany(PresensiMahasiswa::class);
    }

    /**
     * Pertemuan yang ikut dihitung dalam persentase kehadiran: kuliah biasa yang sudah selesai.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDihitung(Builder $query): void
    {
        $query->where('jenis', self::KULIAH)->where('status', self::SELESAI);
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
     * Rentang waktu dosen boleh menekan "Mulai Kuliah". Admin tidak terikat rentang ini.
     */
    public function bisaDimulaiDosen(?Carbon $sekarang = null): bool
    {
        $sekarang ??= now();

        return $this->status === self::DIJADWALKAN
            && $sekarang->between($this->mulaiAt()->subMinutes(self::BUKA_LEBIH_AWAL_MENIT), $this->akhirAt());
    }

    /**
     * Tutup pertemuan yang masih berlangsung padahal sudah lewat jam akhir + toleransi. Dipanggil saat
     * halaman presensi dibuka, sehingga tidak butuh penjadwal (cron) di server.
     */
    public static function tutupYangLewat(): void
    {
        $batas = now()->subMinutes(self::TUTUP_OTOMATIS_MENIT);

        static::query()
            ->where('status', self::BERLANGSUNG)
            ->whereDate('tanggal', '<=', $batas->toDateString())
            ->get()
            ->filter(fn (self $pertemuan): bool => $pertemuan->akhirAt()->lt($batas))
            ->each(fn (self $pertemuan) => $pertemuan->update([
                'status' => self::SELESAI,
                'dosen_keluar_at' => $pertemuan->dosen_keluar_at ?? $pertemuan->akhirAt(),
                'mandiri_sampai' => null,
            ]));
    }

    public function mandiriTerbuka(): bool
    {
        return $this->status === self::BERLANGSUNG && $this->mandiri_sampai !== null && $this->mandiri_sampai->isFuture();
    }

    public static function periodeKode(?Carbon $waktu = null): int
    {
        return intdiv(($waktu ?? now())->getTimestamp(), self::PERIODE_KODE_DETIK);
    }

    /**
     * PIN 6 angka (diketik) dan token QR untuk satu periode 30 detik. Keduanya dihitung dari rahasia
     * pertemuan, jadi tidak perlu disimpan setiap kali berganti.
     *
     * @return array{pin: string, token: string}
     */
    public function kodeUntuk(int $periode): array
    {
        $hash = hash_hmac('sha256', $this->id.'|'.$periode, (string) $this->kode_rahasia);

        return [
            'pin' => str_pad((string) (hexdec(substr($hash, -8)) % 1000000), 6, '0', STR_PAD_LEFT),
            'token' => substr($hash, 0, 16),
        ];
    }

    /**
     * Cocokkan PIN atau token QR dengan periode sekarang dan beberapa periode sebelumnya.
     */
    public function kodeCocok(string $kode): bool
    {
        if ($this->kode_rahasia === null || $kode === '') {
            return false;
        }

        $sekarang = self::periodeKode();

        for ($periode = $sekarang; $periode >= $sekarang - self::PERIODE_KODE_TOLERANSI; $periode--) {
            $benar = $this->kodeUntuk($periode);

            if (hash_equals($benar['pin'], $kode) || hash_equals($benar['token'], $kode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Status presensi mandiri: Hadir bila masuk sebelum jam mulai + toleransi, selebihnya Terlambat.
     * Bila dosen sendiri datang terlambat, hitungan dimulai dari jam masuk dosen.
     */
    public function statusPresensiMandiri(?Carbon $waktu = null): string
    {
        $acuan = $this->mulaiAt();

        if ($this->dosen_masuk_at !== null && $this->dosen_masuk_at->gt($acuan)) {
            $acuan = $this->dosen_masuk_at->copy();
        }

        $batas = $acuan->addMinutes(PengaturanAkademik::current()->toleransi_terlambat_menit);

        return ($waktu ?? now())->lte($batas) ? PresensiMahasiswa::HADIR : PresensiMahasiswa::TERLAMBAT;
    }

    /**
     * Buat baris presensi (bawaan Alpa) untuk peserta KRS yang belum punya. Mahasiswa yang masuk kelas
     * sesudah pertemuan selesai tidak ditambahkan, jadi pertemuan sebelum ia terdaftar tidak dihitung.
     */
    public function siapkanPeserta(): void
    {
        $sudahAda = $this->presensiMahasiswas()->pluck('mahasiswa_id');

        $baru = Krs::query()
            ->where('kelas_id', $this->kelas_id)
            ->whereNotIn('mahasiswa_id', $sudahAda)
            ->pluck('mahasiswa_id')
            ->unique()
            ->map(fn (int $mahasiswaId): array => [
                'pertemuan_id' => $this->id,
                'mahasiswa_id' => $mahasiswaId,
                'status' => PresensiMahasiswa::ALPA,
                'metode' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if ($baru->isNotEmpty()) {
            PresensiMahasiswa::query()->insertOrIgnore($baru->all());
        }
    }

    /**
     * Buat pertemuan yang belum ada (nomor 1..jumlah_pertemuan) dari jadwal mingguan kelas, dimulai dari
     * tanggal mulai tahun akademik. Pertemuan yang sudah ada tidak diubah.
     *
     * @return array{dibuat: int, lewat_akhir: int}
     */
    public static function generateUntuk(KelasKuliah $kelas): array
    {
        $kelas->loadMissing('tahunAkademik', 'jadwals');
        $jadwals = $kelas->jadwals->sortBy(fn (Jadwal $jadwal): string => self::urutanHari($jadwal->hari).$jadwal->jam_mulai)->values();

        if ($jadwals->isEmpty()) {
            throw ValidationException::withMessages(['pertemuan' => 'Kelas ini belum punya jadwal. Tambahkan jadwal mingguan terlebih dahulu.']);
        }

        $jumlah = $kelas->jumlah_pertemuan;
        $slot = [];
        $tanggal = $kelas->tahunAkademik->tanggal_mulai->copy();

        // Batas satu tahun mencegah perulangan tanpa akhir bila hari jadwal tidak valid.
        for ($hari = 0; count($slot) < $jumlah && $hari < 366; $hari++, $tanggal->addDay()) {
            foreach ($jadwals->where('hari', self::NAMA_HARI[$tanggal->dayOfWeekIso] ?? null) as $jadwal) {
                $slot[] = [$tanggal->copy(), $jadwal];
            }
        }

        $ada = $kelas->pertemuans()->pluck('jenis', 'pertemuan_ke');
        $nomorUts = $jumlah >= 4 && ! $ada->contains(self::UTS) ? intdiv($jumlah, 2) : null;
        $nomorUas = $jumlah >= 4 && ! $ada->contains(self::UAS) ? $jumlah : null;
        $hasil = ['dibuat' => 0, 'lewat_akhir' => 0];

        DB::transaction(function () use ($kelas, $jumlah, $slot, $ada, $nomorUts, $nomorUas, &$hasil): void {
            for ($ke = 1; $ke <= $jumlah && isset($slot[$ke - 1]); $ke++) {
                if ($ada->has($ke)) {
                    continue;
                }

                [$tanggal, $jadwal] = $slot[$ke - 1];

                static::create([
                    'kelas_id' => $kelas->id,
                    'pertemuan_ke' => $ke,
                    'tanggal' => $tanggal,
                    'jam_mulai' => $jadwal->jam_mulai,
                    'jam_akhir' => $jadwal->jam_akhir,
                    'ruang_id' => $jadwal->ruang_id,
                    'jenis' => match ($ke) {
                        $nomorUts => self::UTS,
                        $nomorUas => self::UAS,
                        default => self::KULIAH,
                    },
                    'status' => self::DIJADWALKAN,
                    'dosen_id' => $kelas->dosen_id,
                ]);

                $hasil['dibuat']++;

                if ($kelas->tahunAkademik->tanggal_akhir && $tanggal->gt($kelas->tahunAkademik->tanggal_akhir)) {
                    $hasil['lewat_akhir']++;
                }
            }
        });

        return $hasil;
    }

    /**
     * Pertemuan lain pada tanggal & jam yang beririsan yang memakai ruang yang sama atau diajar dosen yang sama.
     */
    public static function bentrok(KelasKuliah $kelas, string $tanggal, string $jamMulai, string $jamAkhir, ?int $ruangId, ?int $dosenId, ?int $kecualiId): ?self
    {
        return static::query()
            ->whereDate('tanggal', $tanggal)
            ->where('status', '!=', self::DIBATALKAN)
            ->where('jam_mulai', '<', $jamAkhir)
            ->where('jam_akhir', '>', $jamMulai)
            ->when($kecualiId, fn ($query) => $query->whereKeyNot($kecualiId))
            ->where(fn ($query) => $query
                ->where('kelas_id', $kelas->id)
                ->when($ruangId, fn ($q) => $q->orWhere('ruang_id', $ruangId))
                ->when($dosenId, fn ($q) => $q->orWhere('dosen_id', $dosenId)))
            ->with('kelasKuliah:id,kode_kelas')
            ->first();
    }

    private static function urutanHari(string $hari): int
    {
        return (int) array_search($hari, self::NAMA_HARI, true);
    }

    public function pengajuanIzins(): HasMany
    {
        return $this->hasMany(PengajuanIzin::class);
    }
}
