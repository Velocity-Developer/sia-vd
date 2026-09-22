<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['nama_quiz', 'catatan', 'waktu_pengerjaan', 'tenggat_waktu', 'uploaded_by', 'kelas_id'];

    protected function casts(): array
    {
        return [
            'waktu_pengerjaan' => 'integer',
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

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'quiz_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
