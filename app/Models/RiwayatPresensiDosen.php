<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak koreksi presensi dosen oleh admin dan keputusan verifikasinya (setujui, tolak, batal).
 */
class RiwayatPresensiDosen extends Model
{
    use SerializesDatesInAppTimezone;

    public const KOREKSI = 'koreksi';

    public const SETUJUI = 'setujui';

    public const TOLAK = 'tolak';

    public const BATAL = 'batal_verifikasi';

    public const LABEL = [self::KOREKSI => 'Koreksi', self::SETUJUI => 'Disetujui', self::TOLAK => 'Ditolak', self::BATAL => 'Verifikasi dibatalkan'];

    protected $fillable = ['pertemuan_id', 'aksi', 'perubahan', 'alasan', 'diubah_oleh'];

    protected function casts(): array
    {
        return ['perubahan' => 'array'];
    }

    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(Pertemuan::class);
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    /**
     * @return array{id: int, aksi: string, perubahan: array<string, array{0: mixed, 1: mixed}>, alasan: ?string, oleh: ?string, waktu: ?string}
     */
    public function ringkas(): array
    {
        return [
            'id' => $this->id,
            'aksi' => self::LABEL[$this->aksi] ?? $this->aksi,
            'perubahan' => $this->perubahan ?? [],
            'alasan' => $this->alasan,
            'oleh' => $this->pengubah?->name,
            'waktu' => $this->created_at?->toIso8601String(),
        ];
    }
}
