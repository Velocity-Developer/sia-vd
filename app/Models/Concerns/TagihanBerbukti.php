<?php

namespace App\Models\Concerns;

use App\Models\JenisBiaya;
use App\Models\MahasiswaProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tagihan yang dibayar lewat unggah bukti lalu diverifikasi admin (tagihan remidi, tagihan susulan).
 * Tagihan yang belum lunas saat batas bayar lewat dianggap gugur (tidak disimpan sebagai status).
 * Kolom yang dipakai: status, bukti, bukti_diunggah_at, alasan_tolak, diverifikasi_oleh, diverifikasi_at.
 */
trait TagihanBerbukti
{
    public const BELUM_BAYAR = 'belum_bayar';

    public const MENUNGGU = 'menunggu_verifikasi';

    public const LUNAS = 'lunas';

    public const DITOLAK = 'ditolak';

    /** Status tampilan saja: belum lunas dan batas bayar sudah lewat. */
    public const GUGUR = 'gugur';

    public const EKSTENSI_BUKTI = ['pdf', 'jpg', 'jpeg', 'png'];

    abstract public function batasBayar(): ?Carbon;

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
     * Rincian dari jenis biaya: cara hitung tetap = sekali per tagihan, per SKS = dikali SKS mata kuliahnya.
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
