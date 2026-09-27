<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pengajuan ujian susulan oleh mahasiswa yang tidak mengikuti UTS/UAS (ujian utama).
 *
 * Status "tampilan" diturunkan dari keikutsertaan di ujian utama (lihat App\UjianSusulan), bukan disimpan:
 * pengajuan menunggu milik mahasiswa yang ternyata ikut ujian utama dianggap dibatalkan, dan yang sudah
 * disetujui kehilangan hak susulannya (gugur).
 */
class PengajuanSusulan extends Model
{
    use SerializesDatesInAppTimezone;

    public const MENUNGGU = 'menunggu';

    public const DISETUJUI = 'disetujui';

    public const DITOLAK = 'ditolak';

    public const DIBATALKAN = 'dibatalkan';

    /** Status tampilan saja: disetujui, tetapi mahasiswa ternyata mengikuti ujian utama. */
    public const GUGUR = 'gugur';

    /** Status yang menghalangi pengajuan baru untuk ujian yang sama. */
    public const AKTIF = [self::MENUNGGU, self::DISETUJUI];

    public const EKSTENSI_LAMPIRAN = ['pdf', 'jpg', 'jpeg', 'png'];

    protected $table = 'pengajuan_susulan';

    protected $fillable = ['ujian_id', 'mahasiswa_id', 'alasan', 'lampiran', 'status', 'catatan_admin', 'diproses_oleh', 'diproses_at'];

    protected function casts(): array
    {
        return [
            'lampiran' => 'array',
            'diproses_at' => 'datetime',
        ];
    }

    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function tagihan(): HasOne
    {
        return $this->hasOne(TagihanSusulan::class, 'pengajuan_susulan_id');
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->whereIn('status', self::AKTIF);
    }

    public function statusTampil(bool $ikutUjianUtama): string
    {
        return match (true) {
            $this->status === self::MENUNGGU && $ikutUjianUtama => self::DIBATALKAN,
            $this->status === self::DISETUJUI && $ikutUjianUtama => self::GUGUR,
            default => $this->status,
        };
    }
}
