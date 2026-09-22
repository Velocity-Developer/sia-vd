<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanPindahKelas extends Model
{
    use SerializesDatesInAppTimezone;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    protected $table = 'pengajuan_pindah_kelas';

    protected $fillable = [
        'mahasiswa_id',
        'kelas_asal_id',
        'kelas_tujuan_id',
        'alasan',
        'status',
        'diproses_oleh',
        'diproses_at',
        'catatan_admin',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'diproses_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function kelasAsal(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_asal_id');
    }

    public function kelasTujuan(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_tujuan_id');
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
