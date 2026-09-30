<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Models\Concerns\TagihanBerbukti;
use App\SyaratTugasAkhir;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tagihan semester satu mahasiswa. Alurnya: admin menerbitkan, mahasiswa mengunggah bukti bayar,
 * lalu admin menandai lunas atau menolak buktinya. Tidak punya batas bayar, jadi tidak pernah gugur.
 */
class TagihanSemester extends Model
{
    use SerializesDatesInAppTimezone;
    use TagihanBerbukti;

    protected $table = 'tagihan_semester';

    protected $fillable = [
        'mahasiswa_id', 'tahun_akademik_id', 'status', 'total', 'rincian_manual', 'tanggal_lunas', 'diubah_oleh',
        'bukti', 'bukti_diunggah_at', 'alasan_tolak', 'diverifikasi_oleh', 'diverifikasi_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'rincian_manual' => 'boolean',
            'tanggal_lunas' => 'date',
            'bukti_diunggah_at' => 'datetime',
            'diverifikasi_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Tanggal lunas mengikuti status, dari jalur mana pun tagihan itu dilunasi atau dibatalkan.
        static::saving(function (TagihanSemester $tagihan): void {
            if ($tagihan->status !== self::LUNAS) {
                $tagihan->tanggal_lunas = null;
            } elseif ($tagihan->tanggal_lunas === null) {
                $tagihan->tanggal_lunas = today();
            }
        });
    }

    public function batasBayar(): ?Carbon
    {
        return null;
    }

    /**
     * Tagihan hanya dihitung ulang saat diterbitkan ulang bila belum dibayar, belum ada bukti,
     * dan rinciannya tidak diketik admin.
     */
    public function bolehDihitungUlang(): bool
    {
        return $this->status === self::BELUM_BAYAR && $this->bukti === null && ! $this->rincian_manual;
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TagihanItem::class, 'tagihan_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    public function lunas(): bool
    {
        return $this->status === self::LUNAS;
    }

    /**
     * Rekap tagihan mahasiswa aktif pada satu tahun akademik (dipakai daftar Tagihan Mahasiswa dan dashboard admin).
     * Tagihan ditolak ikut dihitung belum bayar karena mahasiswa masih harus mengunggah ulang.
     *
     * @return array{total: int, terbit: int, belum_terbit: int, lunas: int, menunggu: int, belum_bayar: int, nominal_terbit: int, nominal_lunas: int}
     */
    public static function ringkasan(?int $tahunAkademikId): array
    {
        $total = MahasiswaProfile::query()->where('status', 'Aktif')->whereHas('user')->count();
        $perStatus = self::query()
            ->where('tahun_akademik_id', $tahunAkademikId)
            ->whereHas('mahasiswa', fn (Builder $query) => $query->where('status', 'Aktif')->whereHas('user'))
            ->selectRaw('status, count(*) as jumlah, coalesce(sum(total), 0) as nominal')
            ->groupBy('status')
            ->get()
            ->keyBy('status');
        $jumlah = fn (string $status): int => (int) ($perStatus[$status]->jumlah ?? 0);

        return [
            'total' => $total,
            'terbit' => (int) $perStatus->sum('jumlah'),
            'belum_terbit' => max(0, $total - (int) $perStatus->sum('jumlah')),
            'lunas' => $jumlah(self::LUNAS),
            'menunggu' => $jumlah(self::MENUNGGU),
            'belum_bayar' => $jumlah(self::BELUM_BAYAR) + $jumlah(self::DITOLAK),
            'nominal_terbit' => (int) $perStatus->sum('nominal'),
            'nominal_lunas' => (int) ($perStatus[self::LUNAS]->nominal ?? 0),
        ];
    }

    /**
     * Jumlah SKS yang diambil mahasiswa pada satu tahun akademik, dipakai untuk biaya per SKS.
     */
    public static function sksDiambil(int $mahasiswaId, int $tahunAkademikId): int
    {
        return (int) Krs::query()
            ->join('kelas_kuliah', 'krs.kelas_id', '=', 'kelas_kuliah.id')
            ->join('mata_kuliahs', 'kelas_kuliah.matkul_id', '=', 'mata_kuliahs.id')
            ->where('krs.mahasiswa_id', $mahasiswaId)
            ->where('krs.status', 'Aktif')
            ->where('kelas_kuliah.tahun_akademik_id', $tahunAkademikId)
            ->sum('mata_kuliahs.sks');
    }

    /**
     * Jumlah SKS yang dipakai menghitung biaya per SKS: kuota (batas) SKS mahasiswa pada semester itu.
     *
     * Tagihan terbit sebelum KRS diisi, jadi SKS yang benar-benar diambil belum ada. Yang dipakai
     * adalah batas SKS menurut IPS semester sebelumnya (lihat Pengaturan Akademik), kecuali mahasiswa
     * yang tinggal mengerjakan TA/Skripsi: kuotanya SKS mata kuliah TA/Skripsi saja.
     */
    public static function kuotaSks(MahasiswaProfile $mahasiswa, ?TahunAkademik $tahunAkademik): int
    {
        return self::sksTinggalTa($mahasiswa)
            ?? PengaturanAkademik::maksSksUntuk($mahasiswa->ipsSemesterSebelum($tahunAkademik)['ips'] ?? null);
    }

    /**
     * SKS mata kuliah TA/Skripsi prodi mahasiswa bila ia tinggal mengerjakan TA/Skripsi: syarat nilai pendadaran
     * (SKS minimal, tanpa E, semua dinilai) sudah terpenuhi dan TA/Skripsi belum dinilai. Selain itu null.
     */
    public static function sksTinggalTa(MahasiswaProfile $mahasiswa): ?int
    {
        $matkulTa = MataKuliah::query()->where('prodi_id', $mahasiswa->prodi_id)->where('tugas_akhir', true)->orderBy('id')->first(['id', 'sks']);

        if ($matkulTa === null) {
            return null;
        }

        $sudahDinilai = Krs::query()->where('mahasiswa_id', $mahasiswa->id)->whereNotNull('nilai')
            ->whereHas('kelasKuliah.mataKuliah', fn (Builder $q) => $q->where('tugas_akhir', true))->exists();

        return ! $sudahDinilai && SyaratTugasAkhir::terpenuhi(SyaratTugasAkhir::nilai($mahasiswa, false)) ? $matkulTa->sks : null;
    }

    /**
     * Rincian tagihan dari tarif yang berlaku. Totalnya nol bila tidak ada tarif yang cocok.
     *
     * @param  Collection<int, JenisBiaya>  $jenisBiaya
     * @return list<array<string, mixed>>
     */
    public static function hitungRincian(MahasiswaProfile $mahasiswa, Collection $jenisBiaya, ?TahunAkademik $tahunAkademik): array
    {
        $kuota = self::kuotaSks($mahasiswa, $tahunAkademik);
        $rincian = [];

        foreach ($jenisBiaya as $jenis) {
            $tarif = $jenis->tarifUntuk($mahasiswa->prodi_id, $mahasiswa->angkatan);

            if (! $tarif || $tarif->nominal <= 0) {
                continue;
            }

            $jumlah = $jenis->cara_hitung === JenisBiaya::PER_SKS ? $kuota : 1;

            if ($jumlah <= 0) {
                continue;
            }

            $rincian[] = [
                'jenis_biaya_id' => $jenis->id,
                'nama' => $jenis->nama,
                'cara_hitung' => $jenis->cara_hitung,
                'nominal_satuan' => $tarif->nominal,
                'jumlah' => $jumlah,
                'subtotal' => $tarif->nominal * $jumlah,
            ];
        }

        return $rincian;
    }

    /**
     * Ganti rincian tagihan. Nominalnya disalin (dibekukan), jadi perubahan tarif tidak mengubah
     * tagihan yang sudah terbit kecuali diterbitkan ulang.
     *
     * @param  list<array<string, mixed>>  $rincian
     */
    public function gantiRincian(array $rincian, bool $manual): void
    {
        $this->items()->delete();
        $this->items()->createMany($rincian);
        $this->forceFill(['total' => array_sum(array_column($rincian, 'subtotal')), 'rincian_manual' => $manual])->save();
    }
}
