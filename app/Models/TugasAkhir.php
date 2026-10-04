<?php

namespace App\Models;

use App\Feature;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tugas akhir/skripsi yang disahkan admin dari pengajuan TA: judul, bidang, dan pembimbing (maks. 2). Sesudah disahkan,
 * mahasiswa mengunggah naskah TA (`naskah`, PDF di disk privat) yang sekaligus menjadi naskah final syarat wisuda; terkunci
 * selama pendaftaran wisuda diproses dan sesudah terdaftar sebagai peserta wisuda.
 */
class TugasAkhir extends Model
{
    use SerializesDatesInAppTimezone;

    public const BERJALAN = 'berjalan';

    public const SELESAI = 'selesai';

    protected $table = 'tugas_akhir';

    protected $fillable = ['mahasiswa_id', 'pengajuan_id', 'judul', 'bidang', 'pembimbing_1_id', 'pembimbing_2_id', 'status', 'disahkan_oleh', 'selesai_at', 'naskah', 'naskah_diunggah_at'];

    protected function casts(): array
    {
        return ['selesai_at' => 'datetime', 'naskah_diunggah_at' => 'datetime'];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanAkademik::class, 'pengajuan_id');
    }

    public function pembimbing1(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'pembimbing_1_id');
    }

    public function pembimbing2(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'pembimbing_2_id');
    }

    /**
     * TA mahasiswa yang sedang berjalan atau sudah selesai (satu mahasiswa hanya punya satu).
     */
    public static function milik(int $mahasiswaId): ?self
    {
        return static::query()->where('mahasiswa_id', $mahasiswaId)->latest('id')->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDibimbing(Builder $query, int $dosenId): void
    {
        $query->where(fn (Builder $q) => $q->where('pembimbing_1_id', $dosenId)->orWhere('pembimbing_2_id', $dosenId));
    }

    public function dibimbingOleh(int $dosenId): bool
    {
        return in_array($dosenId, [$this->pembimbing_1_id, $this->pembimbing_2_id], true);
    }

    /**
     * @return list<string>
     */
    public function namaPembimbing(): array
    {
        return array_values(array_filter([$this->pembimbing1?->user?->name, $this->pembimbing2?->user?->name]));
    }

    /**
     * Tanpa pendadaran, TA dinyatakan selesai oleh nilai: selesai selama mahasiswa punya KRS mata kuliah TA/Skripsi
     * dengan huruf lulus, kembali berjalan bila nilai itu dihapus. Dipanggil setiap nilai KRS TA berubah.
     */
    public static function sinkronDariNilai(int $mahasiswaId): void
    {
        if (Feature::aktif('pendadaran') || ($tugasAkhir = static::milik($mahasiswaId)) === null) {
            return;
        }

        $lulus = Krs::query()->where('mahasiswa_id', $mahasiswaId)->whereNotNull('nilai')
            ->whereHas('kelasKuliah.mataKuliah', fn (Builder $q) => $q->where('tugas_akhir', true))
            ->with('kelasKuliah.mataKuliah:id,prodi_id')
            ->get()
            ->contains(fn (Krs $krs): bool => $krs->nilaiLulus());

        $status = $lulus ? self::SELESAI : self::BERJALAN;
        if ($tugasAkhir->status !== $status) {
            $tugasAkhir->update(['status' => $status, 'selesai_at' => $lulus ? now() : null]);
        }
    }
}
