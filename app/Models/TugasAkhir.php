<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tugas akhir/skripsi yang disahkan admin dari pengajuan TA: judul, bidang, dan pembimbing (maks. 2).
 */
class TugasAkhir extends Model
{
    use SerializesDatesInAppTimezone;

    public const BERJALAN = 'berjalan';

    public const SELESAI = 'selesai';

    protected $table = 'tugas_akhir';

    protected $fillable = ['mahasiswa_id', 'pengajuan_id', 'judul', 'bidang', 'pembimbing_1_id', 'pembimbing_2_id', 'status', 'disahkan_oleh', 'selesai_at'];

    protected function casts(): array
    {
        return ['selesai_at' => 'datetime'];
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
}
