<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * Nilai ulang semua attempt yang sudah dikirim setelah soal diubah atau dihapus.
     * Poin esai yang sudah dikoreksi dipertahankan, dibatasi maksimal poin soalnya.
     */
    public function regradeAttempts(): void
    {
        $questions = $this->questions()->get()->keyBy('id');

        DB::transaction(function () use ($questions): void {
            $this->attempts()->whereNotNull('submitted_at')->with('answers')->get()
                ->each(function (QuizAttempt $attempt) use ($questions): void {
                    foreach ($attempt->answers as $answer) {
                        $question = $questions->get($answer->question_id);

                        if ($question === null) {
                            continue;
                        }

                        $point = $question->question_type === 'essay'
                            ? ($answer->point === null ? null : min((float) $answer->point, (float) $question->points))
                            : $question->pointFor($question->question_type === 'multiple_choice' ? ($answer->answer ?? []) : ($answer->answer[0] ?? null));

                        $answer->update(['point' => $point]);
                    }

                    $attempt->recalculateScore();
                });
        });
    }
}
