<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tagihan remidi satu mahasiswa untuk satu kelas. Mahasiswa mengunggah bukti bayar, admin memverifikasi.
 * Tagihan yang belum lunas saat batas bayar lewat dianggap gugur (tidak disimpan sebagai status).
 */
class TagihanRemidi extends Model
{
    use SerializesDatesInAppTimezone;

    public const BELUM_BAYAR = 'belum_bayar';

    public const MENUNGGU = 'menunggu_verifikasi';

    public const LUNAS = 'lunas';

    public const DITOLAK = 'ditolak';

    /** Status tampilan saja: belum lunas dan batas bayar sudah lewat. */
    public const GUGUR = 'gugur';

    public const EKSTENSI_BUKTI = ['pdf', 'jpg', 'jpeg', 'png'];

    protected $table = 'tagihan_remidi';

    protected $fillable = [
        'remidi_peserta_id', 'mahasiswa_id', 'kelas_id', 'rincian', 'total', 'status', 'bukti', 'bukti_diunggah_at',
        'alasan_tolak', 'diverifikasi_oleh', 'diverifikasi_at', 'diterbitkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'rincian' => 'array',
            'total' => 'integer',
            'bukti_diunggah_at' => 'datetime',
            'diverifikasi_at' => 'datetime',
        ];
    }

    public function peserta(): BelongsTo
    {
        return $this->belongsTo(RemidiPeserta::class, 'remidi_peserta_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function batasBayar(): ?Carbon
    {
        return $this->kelasKuliah?->tahunAkademik?->batas_bayar_remidi;
    }

    public function lewatBatas(): bool
    {
        return $this->batasBayar()?->copy()->endOfDay()->isPast() ?? false;
    }

    /**
     * Bukti yang diunggah sebelum batas tetap bisa diverifikasi sesudahnya; yang gugur hanya yang belum bayar atau ditolak.
     */
    public function gugur(): bool
    {
        return in_array($this->status, [self::BELUM_BAYAR, self::DITOLAK], true) && $this->lewatBatas();
    }

    public function statusTampil(): string
    {
        return $this->gugur() ? self::GUGUR : $this->status;
    }

    public function bolehUnggah(): bool
    {
        return $this->status !== self::LUNAS && ! $this->lewatBatas();
    }

    /**
     * Rincian dari jenis biaya kategori remidi: cara hitung tetap = per mata kuliah, per SKS = dikali SKS mata kuliah.
     *
     * @param  Collection<int, JenisBiaya>  $jenisBiaya
     * @return array{rincian: list<array<string, mixed>>, total: int}
     */
    public static function hitung(MahasiswaProfile $mahasiswa, int $sks, Collection $jenisBiaya): array
    {
        $rincian = [];

        foreach ($jenisBiaya as $jenis) {
            $tarif = $jenis->tarifUntuk($mahasiswa->prodi_id, $mahasiswa->angkatan);
            $jumlah = $jenis->cara_hitung === JenisBiaya::PER_SKS ? $sks : 1;

            if (! $tarif || $tarif->nominal <= 0 || $jumlah <= 0) {
                continue;
            }

            $rincian[] = [
                'nama' => $jenis->nama,
                'cara_hitung' => $jenis->cara_hitung,
                'nominal_satuan' => $tarif->nominal,
                'jumlah' => $jumlah,
                'subtotal' => $tarif->nominal * $jumlah,
            ];
        }

        return ['rincian' => $rincian, 'total' => array_sum(array_column($rincian, 'subtotal'))];
    }
}
