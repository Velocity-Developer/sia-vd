<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tugas extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'tugas';

    protected $fillable = ['judul_tugas', 'file', 'tenggat_waktu', 'catatan', 'uploaded_by', 'kelas_id'];

    protected function casts(): array
    {
        return [
            'file' => 'array',
            'tenggat_waktu' => 'datetime',
        ];
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function pengumpulanTugas(): HasMany
    {
        return $this->hasMany(PengumpulanTugas::class);
    }
}
