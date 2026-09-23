<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TagihanSemester extends Model
{
    use SerializesDatesInAppTimezone;

    public const BELUM_BAYAR = 'belum_bayar';

    public const LUNAS = 'lunas';

    protected $table = 'tagihan_semester';

    protected $fillable = ['mahasiswa_id', 'tahun_akademik_id', 'status', 'total', 'tanggal_lunas', 'diubah_oleh'];

    protected function casts(): array
    {
        return ['total' => 'integer', 'tanggal_lunas' => 'date'];
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
     * Susun ulang rincian tagihan dari tarif yang berlaku. Nominalnya disalin ke rincian
     * (dibekukan), jadi perubahan tarif tidak mengubah tagihan yang sudah terbit kecuali
     * disusun ulang dari halaman admin.
     *
     * @param  Collection<int, JenisBiaya>  $jenisBiaya
     */
    public function susunRincian(MahasiswaProfile $mahasiswa, Collection $jenisBiaya): void
    {
        $sks = self::sksDiambil($mahasiswa->id, $this->tahun_akademik_id);
        $total = 0;

        $this->items()->delete();

        foreach ($jenisBiaya as $jenis) {
            $tarif = $jenis->tarifUntuk($mahasiswa->prodi_id, $mahasiswa->angkatan);

            if (! $tarif || $tarif->nominal <= 0) {
                continue;
            }

            $jumlah = $jenis->cara_hitung === JenisBiaya::PER_SKS ? $sks : 1;

            if ($jumlah <= 0) {
                continue;
            }

            $subtotal = $tarif->nominal * $jumlah;
            $total += $subtotal;

            $this->items()->create([
                'jenis_biaya_id' => $jenis->id,
                'nama' => $jenis->nama,
                'cara_hitung' => $jenis->cara_hitung,
                'nominal_satuan' => $tarif->nominal,
                'jumlah' => $jumlah,
                'subtotal' => $subtotal,
            ]);
        }

        $this->forceFill(['total' => $total])->save();
    }
}
