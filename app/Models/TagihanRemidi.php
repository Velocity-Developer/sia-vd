<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Models\Concerns\TagihanBerbukti;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tagihan remidi satu mahasiswa untuk satu kelas. Mahasiswa mengunggah bukti bayar, admin memverifikasi
 * (status dan aturan gugur di TagihanBerbukti). Batas bayar = batas bayar remidi tahun akademik.
 */
class TagihanRemidi extends Model
{
    use SerializesDatesInAppTimezone;
    use TagihanBerbukti;

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
}
