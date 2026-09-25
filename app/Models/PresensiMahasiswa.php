<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Closure;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Hanya presensi mahasiswa yang masih terdaftar (ber-KRS) di kelas pertemuannya, agar rekap, rata-rata
     * kelas, dan laporan tidak ikut menghitung mahasiswa yang sudah pindah atau batal dari kelas itu.
     * Mengembalikan closure untuk whereExists, bisa dipakai di query Eloquent maupun DB::table.
     */
    public static function syaratPesertaAktif(): Closure
    {
        return fn ($query) => $query->selectRaw('1')
            ->from('krs')
            ->join('pertemuans as pertemuan_krs', 'pertemuan_krs.kelas_id', '=', 'krs.kelas_id')
            ->whereColumn('pertemuan_krs.id', 'presensi_mahasiswas.pertemuan_id')
            ->whereColumn('krs.mahasiswa_id', 'presensi_mahasiswas.mahasiswa_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePesertaAktif(Builder $query): void
    {
        $query->whereExists(self::syaratPesertaAktif());
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
     * Pindahkan riwayat presensi, pengajuan izin, dan dispensasi ujian seorang mahasiswa dari kelas asal ke
     * kelas tujuan (dipakai saat pindah kelas disetujui). Presensi pertemuan ke-N di kelas asal pindah ke
     * pertemuan ke-N di kelas tujuan; yang tidak punya pasangan (belum dibuat/dibatalkan, atau sudah terisi)
     * tetap di kelas asal.
     *
     * @return array{dipindah: int, tertinggal: int}
     */
    public static function pindahkanRiwayat(int $mahasiswaId, int $kelasAsalId, int $kelasTujuanId): array
    {
        $tujuan = Pertemuan::query()
            ->where('kelas_id', $kelasTujuanId)
            ->where('status', '!=', Pertemuan::DIBATALKAN)
            ->pluck('id', 'pertemuan_ke');
        $nomorAsal = Pertemuan::query()->where('kelas_id', $kelasAsalId)->pluck('pertemuan_ke', 'id');
        $hasil = ['dipindah' => 0, 'tertinggal' => 0];

        foreach ([self::class, PengajuanIzin::class] as $model) {
            $sudahAda = $model::query()->where('mahasiswa_id', $mahasiswaId)->whereIn('pertemuan_id', $tujuan->values())->pluck('pertemuan_id')->flip();

            foreach ($model::query()->where('mahasiswa_id', $mahasiswaId)->whereIn('pertemuan_id', $nomorAsal->keys())->get() as $baris) {
                $target = $tujuan[$nomorAsal[$baris->pertemuan_id]] ?? null;

                $pindah = $target !== null && ! $sudahAda->has($target);

                if ($pindah) {
                    $baris->update(['pertemuan_id' => $target]);
                }

                // Hitungan untuk pesan admin hanya dari baris presensi, bukan pengajuan izin.
                if ($model === self::class) {
                    $hasil[$pindah ? 'dipindah' : 'tertinggal']++;
                }
            }
        }

        $dispensasiTujuan = DispensasiUjian::query()->where('kelas_id', $kelasTujuanId)->where('mahasiswa_id', $mahasiswaId)->pluck('jenis');
        DispensasiUjian::query()
            ->where('kelas_id', $kelasAsalId)
            ->where('mahasiswa_id', $mahasiswaId)
            ->whereNotIn('jenis', $dispensasiTujuan)
            ->update(['kelas_id' => $kelasTujuanId]);

        return $hasil;
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
            ->pesertaAktif()
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
