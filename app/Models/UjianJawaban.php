<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban ujian online mode unggah berkas (satu per mahasiswa per ujian).
 */
class UjianJawaban extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'ujian_jawabans';

    protected $fillable = ['ujian_id', 'mahasiswa_id', 'berkas', 'dikumpulkan_at', 'nilai', 'catatan_dosen', 'dinilai_oleh'];

    protected function casts(): array
    {
        return [
            'berkas' => 'array',
            'dikumpulkan_at' => 'datetime',
            'nilai' => 'decimal:2',
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

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dinilai_oleh');
    }
}
