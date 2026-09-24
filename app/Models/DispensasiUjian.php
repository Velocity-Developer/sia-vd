<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Izin mengikuti UTS/UAS walaupun kehadiran di bawah batas, diberikan admin atau kaprodi.
 */
class DispensasiUjian extends Model
{
    use SerializesDatesInAppTimezone;

    public const JENIS = [Pertemuan::UTS, Pertemuan::UAS];

    protected $fillable = ['kelas_id', 'mahasiswa_id', 'jenis', 'alasan', 'diberikan_oleh'];

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function pemberi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diberikan_oleh');
    }
}
