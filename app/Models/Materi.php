<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Materi extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'materis';

    protected $fillable = ['judul_materi', 'pertemuan_ke', 'jenis', 'file', 'catatan', 'uploaded_by', 'kelas_id'];

    protected function casts(): array
    {
        return [
            'pertemuan_ke' => 'integer',
            'file' => 'array',
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
}
