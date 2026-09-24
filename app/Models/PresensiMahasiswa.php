<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class PresensiMahasiswa extends Model
{
    use SerializesDatesInAppTimezone;

    public const HADIR = 'hadir';

    public const TERLAMBAT = 'terlambat';

    public const IZIN = 'izin';

    public const SAKIT = 'sakit';

    public const ALPA = 'alpa';

    public const STATUS = [self::HADIR, self::TERLAMBAT, self::IZIN, self::SAKIT, self::ALPA];

    /**
     * Status yang dihitung hadir. Izin dan sakit tetap dihitung tidak hadir (kebijakan kampus).
     */
    public const DIHITUNG_HADIR = [self::HADIR, self::TERLAMBAT];

    protected $table = 'presensi_mahasiswas';

    protected $fillable = ['pertemuan_id', 'mahasiswa_id', 'status', 'waktu_presensi', 'metode', 'diubah_oleh', 'keterangan', 'ip', 'perangkat'];

    protected $hidden = ['ip', 'perangkat'];

    protected function casts(): array
    {
        return [
            'waktu_presensi' => 'datetime',
        ];
    }

    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(Pertemuan::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    /**
     * Rekap kehadiran per mahasiswa di satu kelas, hanya dari pertemuan yang dihitung. Pembaginya adalah
     * pertemuan yang ia ikuti sejak terdaftar (punya baris presensi), bukan seluruh pertemuan kelas.
     *
     * @return Collection<int, array{hadir: int, terlambat: int, izin: int, sakit: int, alpa: int, dihitung: int, persen: ?float}>
     */
    public static function rekapKelas(int $kelasId): Collection
    {
        return static::query()
            ->whereHas('pertemuan', fn ($query) => $query->where('kelas_id', $kelasId)->dihitung())
            ->selectRaw('mahasiswa_id, status, COUNT(*) as jumlah')
            ->groupBy('mahasiswa_id', 'status')
            ->get()
            ->groupBy('mahasiswa_id')
            ->map(fn (Collection $baris): array => self::ringkas($baris));
    }

    /**
     * Rekap satu mahasiswa di beberapa kelas, dengan aturan yang sama seperti rekapKelas.
     *
     * @param  list<int>  $kelasIds
     * @return Collection<int, array{hadir: int, terlambat: int, izin: int, sakit: int, alpa: int, dihitung: int, persen: ?float}>
     */
    public static function rekapMahasiswa(int $mahasiswaId, array $kelasIds): Collection
    {
        return static::query()
            ->join('pertemuans', 'pertemuans.id', '=', 'presensi_mahasiswas.pertemuan_id')
            ->where('presensi_mahasiswas.mahasiswa_id', $mahasiswaId)
            ->whereIn('pertemuans.kelas_id', $kelasIds)
            ->where('pertemuans.jenis', Pertemuan::KULIAH)
            ->where('pertemuans.status', Pertemuan::SELESAI)
            ->selectRaw('pertemuans.kelas_id, presensi_mahasiswas.status, COUNT(*) as jumlah')
            ->groupBy('pertemuans.kelas_id', 'presensi_mahasiswas.status')
            ->get()
            ->groupBy('kelas_id')
            ->map(fn (Collection $baris): array => self::ringkas($baris));
    }

    /**
     * @param  Collection<int, self>  $baris  hasil hitungan per status
     * @return array{hadir: int, terlambat: int, izin: int, sakit: int, alpa: int, dihitung: int, persen: ?float}
     */
    private static function ringkas(Collection $baris): array
    {
        $jumlah = collect(self::STATUS)->mapWithKeys(fn (string $status): array => [
            $status => (int) ($baris->firstWhere('status', $status)?->jumlah ?? 0),
        ])->all();
        $dihitung = array_sum($jumlah);
        $hadir = $jumlah[self::HADIR] + $jumlah[self::TERLAMBAT];

        return [...$jumlah, 'dihitung' => $dihitung, 'persen' => $dihitung > 0 ? round($hadir / $dihitung * 100, 1) : null];
    }
}
