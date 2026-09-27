<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Periode wisuda yang dibuat admin: pendaftaran dibuka sampai batas daftar selama kuota masih ada.
 */
class PeriodeWisuda extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'periode_wisuda';

    protected $fillable = ['nama', 'tanggal_acara', 'tempat', 'batas_daftar', 'kuota'];

    protected function casts(): array
    {
        return ['tanggal_acara' => 'date', 'batas_daftar' => 'date', 'kuota' => 'integer'];
    }

    public function wisuda(): HasMany
    {
        return $this->hasMany(Wisuda::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDibuka(Builder $query): void
    {
        $query->whereDate('batas_daftar', '>=', today());
    }

    public function dibuka(): bool
    {
        return today()->lessThanOrEqualTo($this->batas_daftar);
    }

    public function sisaKuota(): ?int
    {
        return $this->kuota === null ? null : max(0, $this->kuota - $this->wisuda()->count());
    }

    /**
     * Masih menerima pendaftar: belum lewat batas dan kuota belum penuh.
     */
    public function bisaDidaftar(): bool
    {
        return $this->dibuka() && ($this->kuota === null || $this->sisaKuota() > 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function ringkas(): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'tanggal_acara' => $this->tanggal_acara->toDateString(),
            'tempat' => $this->tempat,
            'batas_daftar' => $this->batas_daftar->toDateString(),
            'kuota' => $this->kuota,
            'sisa_kuota' => $this->sisaKuota(),
        ];
    }
}
