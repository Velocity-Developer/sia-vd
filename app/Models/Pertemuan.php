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
        'kelas_id', 'jadwal_id', 'jadwal_manual', 'pertemuan_ke', 'tanggal', 'jam_mulai', 'jam_akhir', 'ruang_id', 'jenis', 'status',
        'dosen_id', 'dosen_masuk_at', 'dosen_keluar_at', 'topik', 'catatan', 'kode_rahasia', 'mandiri_sampai',
    ];

    protected $hidden = ['kode_rahasia'];

    protected $appends = ['terlewat'];

    protected function casts(): array
    {
        return [
            'pertemuan_ke' => 'integer',
            'tanggal' => 'date:Y-m-d',
            'dosen_masuk_at' => 'datetime',
            'dosen_keluar_at' => 'datetime',
            'mandiri_sampai' => 'datetime',
            'jadwal_manual' => 'boolean',
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

    public function pengajuanIzins(): HasMany
    {
        return $this->hasMany(PengajuanIzin::class);
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
     * Pertemuan hanya bisa dibuka mulai jam yang tertera (tidak lebih awal), agar presensi mandiri tidak
     * mencatat Hadir sebelum kuliah dimulai. Dosen: jam mulai s.d. jam akhir. Admin: kapan saja sesudah jam
     * mulai (untuk mencatat susulan).
     */
    public function bisaDimulaiDosen(?Carbon $sekarang = null): bool
    {
        $sekarang ??= now();

        return $this->status === self::DIJADWALKAN && $sekarang->between($this->mulaiAt(), $this->akhirAt());
    }

    public function bisaDimulaiAdmin(?Carbon $sekarang = null): bool
    {
        return $this->status === self::DIJADWALKAN && ($sekarang ?? now())->gte($this->mulaiAt());
    }

    /**
     * Terlewat: jam akhirnya sudah lewat tetapi pertemuan tidak pernah dimulai (hanya admin yang bisa
     * mencatatnya sebagai susulan). Bukan status tersimpan, dihitung saat ditampilkan.
     */
    public function terlewat(): bool
    {
        return $this->status === self::DIJADWALKAN && $this->akhirAt()->isPast();
    }

    protected function getTerlewatAttribute(): bool
    {
        // Model yang dimuat tanpa kolom tanggal/jam (select terbatas) tidak bisa dinilai.
        return isset($this->attributes['tanggal'], $this->attributes['jam_akhir'], $this->attributes['status']) && $this->terlewat();
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
        // Presensi mandiri berhenti paling lambat di jam akhir pertemuan.
        return $this->status === self::BERLANGSUNG && $this->mandiri_sampai !== null && $this->mandiri_sampai->isFuture() && $this->akhirAt()->isFuture();
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
     * Urutan slot kuliah dari jadwal mingguan kelas, mulai tanggal mulai tahun akademik: slot ke-n dipakai
     * pertemuan ke-n.
     *
     * @return list<array{0: Carbon, 1: Jadwal}>
     */
    public static function slotJadwal(KelasKuliah $kelas, int $jumlah): array
    {
        $kelas->loadMissing('tahunAkademik', 'jadwals');
        $jadwals = $kelas->jadwals->sortBy(fn (Jadwal $jadwal): string => self::urutanHari($jadwal->hari).$jadwal->jam_mulai)->values();

        if ($jadwals->isEmpty()) {
            throw ValidationException::withMessages(['pertemuan' => 'Kelas ini belum punya jadwal. Tambahkan jadwal mingguan terlebih dahulu.']);
        }

        $slot = [];
        $tanggal = $kelas->tahunAkademik->tanggal_mulai->copy();

        // Batas satu tahun mencegah perulangan tanpa akhir bila hari jadwal tidak valid.
        for ($hari = 0; count($slot) < $jumlah && $hari < 366; $hari++, $tanggal->addDay()) {
            foreach ($jadwals->where('hari', self::NAMA_HARI[$tanggal->dayOfWeekIso] ?? null) as $jadwal) {
                $slot[] = [$tanggal->copy(), $jadwal];
            }
        }

        return $slot;
    }

    /**
     * Buat pertemuan yang belum ada (nomor 1..jumlah_pertemuan) dari jadwal mingguan kelas, dimulai dari
     * tanggal mulai tahun akademik. Pertemuan yang sudah ada tidak diubah. UAS selalu di nomor terakhir.
     *
     * @return array{dibuat: int, lewat_akhir: int}
     */
    public static function generateUntuk(KelasKuliah $kelas): array
    {
        $jumlah = $kelas->jumlah_pertemuan;
        $slot = self::slotJadwal($kelas, $jumlah);

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
                    'jadwal_id' => $jadwal->id,
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
     * Susun ulang tanggal, jam, dan ruang pertemuan yang belum dimulai mengikuti jadwal mingguan dan tanggal
     * tahun akademik terbaru. Pertemuan yang dijadwal ulang manual, sudah berjalan, atau yang tanggal lama
     * maupun barunya sudah lewat tidak disentuh.
     *
     * @return array{diubah: int, dilewati: int}
     */
    public static function susunUlang(KelasKuliah $kelas): array
    {
        $kelas->unsetRelation('jadwals')->unsetRelation('tahunAkademik');
        $slot = self::slotJadwal($kelas, $kelas->jumlah_pertemuan);
        $hasil = ['diubah' => 0, 'dilewati' => 0];

        DB::transaction(function () use ($kelas, $slot, &$hasil): void {
            foreach ($kelas->pertemuans()->where('status', self::DIJADWALKAN)->where('jadwal_manual', false)->get() as $pertemuan) {
                $baru = $slot[$pertemuan->pertemuan_ke - 1] ?? null;

                if ($baru === null) {
                    continue;
                }

                [$tanggal, $jadwal] = $baru;
                $sama = $pertemuan->tanggal->isSameDay($tanggal) && $pertemuan->jam_mulai === $jadwal->jam_mulai
                    && $pertemuan->jam_akhir === $jadwal->jam_akhir && $pertemuan->ruang_id === $jadwal->ruang_id;

                if ($sama) {
                    continue;
                }

                if ($pertemuan->tanggal->lt(today()) || $tanggal->lt(today())) {
                    $hasil['dilewati']++;

                    continue;
                }

                $pertemuan->update([
                    'jadwal_id' => $jadwal->id,
                    'tanggal' => $tanggal,
                    'jam_mulai' => $jadwal->jam_mulai,
                    'jam_akhir' => $jadwal->jam_akhir,
                    'ruang_id' => $jadwal->ruang_id,
                ]);
                $hasil['diubah']++;
            }
        });

        return $hasil;
    }

    /**
     * Pesan bentrok bila pada tanggal & jam itu ruang atau dosennya sudah dipakai: oleh pertemuan lain, atau
     * oleh jadwal mingguan kelas lain yang pertemuannya belum dibuat. Null bila tidak bentrok.
     */
    public static function bentrok(KelasKuliah $kelas, string $tanggal, string $jamMulai, string $jamAkhir, ?int $ruangId, ?int $dosenId, ?int $kecualiId): ?string
    {
        $pertemuan = static::query()
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

        if ($pertemuan !== null) {
            return 'Bentrok dengan pertemuan ke-'.$pertemuan->pertemuan_ke.' kelas '.($pertemuan->kelasKuliah?->kode_kelas ?? '-')
                .' ('.substr($pertemuan->jam_mulai, 0, 5).'–'.substr($pertemuan->jam_akhir, 0, 5).') pada ruang atau dosen yang sama.';
        }

        // Kelas yang belum membuat pertemuan hanya punya jadwal mingguan; jadwal itu tetap dianggap terpakai.
        $jadwal = Jadwal::query()
            ->overlapping(self::NAMA_HARI[Carbon::parse($tanggal)->dayOfWeekIso], $jamMulai, $jamAkhir)
            ->inTahunAkademik($kelas->tahun_akademik_id)
            ->where('kelas_id', '!=', $kelas->id)
            ->whereDoesntHave('kelasKuliah.pertemuans')
            ->where(fn ($query) => $query
                ->when($ruangId, fn ($q) => $q->where('ruang_id', $ruangId))
                ->when($dosenId, fn ($q) => $q->orWhereHas('kelasKuliah', fn ($k) => $k->where('dosen_id', $dosenId)))
                ->when(! $ruangId && ! $dosenId, fn ($q) => $q->whereRaw('1 = 0')))
            ->with('kelasKuliah:id,kode_kelas')
            ->first();

        return $jadwal === null ? null
            : 'Bentrok dengan jadwal mingguan kelas '.($jadwal->kelasKuliah?->kode_kelas ?? '-').' ('.$jadwal->hari.' '
                .substr($jadwal->jam_mulai, 0, 5).'–'.substr($jadwal->jam_akhir, 0, 5).') pada ruang atau dosen yang sama.';
    }

    private static function urutanHari(string $hari): int
    {
        return (int) array_search($hari, self::NAMA_HARI, true);
    }
}
