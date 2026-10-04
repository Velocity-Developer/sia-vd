<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Gelombang ujian komprehensif: pendaftaran dibuka dari tanggal buka sampai tanggal tutup selama kuota masih ada.
 * Pendaftar = pengajuan ujian komprehensif yang memilih gelombang ini dan tidak ditolak.
 */
class GelombangKompre extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'gelombang_kompre';

    protected $fillable = ['nama', 'tanggal_buka', 'tanggal_tutup', 'tanggal_ujian', 'kuota', 'keterangan'];

    protected function casts(): array
    {
        return ['tanggal_buka' => 'date', 'tanggal_tutup' => 'date', 'tanggal_ujian' => 'date', 'kuota' => 'integer'];
    }

    /**
     * Pengajuan ujian komprehensif yang memilih gelombang ini (selain yang ditolak).
     *
     * @return Builder<PengajuanAkademik>
     */
    public function pendaftar(): Builder
    {
        return PengajuanAkademik::query()
            ->where('jenis', PengajuanAkademik::KOMPRE)
            ->where('isian->gelombang_kompre_id', $this->id)
            ->where('status', '!=', PengajuanAkademik::DITOLAK);
    }

    public function dibuka(): bool
    {
        return today()->betweenIncluded($this->tanggal_buka, $this->tanggal_tutup);
    }

    public function sisaKuota(?int $kecualiPengajuanId = null): ?int
    {
        if ($this->kuota === null) {
            return null;
        }

        return max(0, $this->kuota - $this->pendaftar()->when($kecualiPengajuanId, fn (Builder $q, int $id) => $q->whereKeyNot($id))->count());
    }

    /**
     * Masih menerima pendaftar. Saat mahasiswa mengirim perbaikan, pengajuannya sendiri tidak dihitung.
     */
    public function bisaDidaftar(?int $kecualiPengajuanId = null): bool
    {
        return $this->dibuka() && ($this->kuota === null || $this->sisaKuota($kecualiPengajuanId) > 0);
    }

    /**
     * Gelombang yang sedang menerima pendaftar, urut tanggal ujian.
     *
     * @return Collection<int, self>
     */
    public static function terbuka(?int $kecualiPengajuanId = null): Collection
    {
        return static::query()->whereDate('tanggal_buka', '<=', today())->whereDate('tanggal_tutup', '>=', today())
            ->orderBy('tanggal_ujian')->get()
            ->filter(fn (self $g): bool => $g->bisaDidaftar($kecualiPengajuanId))->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function ringkas(): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'tanggal_buka' => $this->tanggal_buka->toDateString(),
            'tanggal_tutup' => $this->tanggal_tutup->toDateString(),
            'tanggal_ujian' => $this->tanggal_ujian->toDateString(),
            'kuota' => $this->kuota,
            'sisa_kuota' => $this->sisaKuota(),
            'keterangan' => $this->keterangan,
        ];
    }
}
