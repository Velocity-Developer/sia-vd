<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class QuizAttempt extends Model
{
    use SerializesDatesInAppTimezone;

    /**
     * Toleransi keterlambatan kiriman (detik) agar kiriman otomatis saat waktu habis
     * tetap diterima meski sampai di server sedikit terlambat.
     */
    public const SUBMIT_GRACE_SECONDS = 60;

    protected $fillable = ['quiz_id', 'mahasiswa_id', 'started_at', 'submitted_at', 'score', 'auto_closed'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'auto_closed' => 'boolean',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }

    /**
     * Batas akhir pengerjaan: yang lebih dulu antara durasi pengerjaan dan tenggat quiz.
     */
    public function deadline(): ?CarbonInterface
    {
        $durationEnd = $this->quiz->waktu_pengerjaan
            ? $this->started_at->copy()->addMinutes($this->quiz->waktu_pengerjaan)
            : null;

        return collect([$durationEnd, $this->quiz->tenggat_waktu])->filter()->sort()->first();
    }

    public function isPastSubmitWindow(): bool
    {
        $deadline = $this->deadline();

        return $deadline !== null && now()->greaterThan($deadline->copy()->addSeconds(self::SUBMIT_GRACE_SECONDS));
    }

    /**
     * Tutup attempt yang melewati batas waktu tanpa kiriman: jawaban terakhir yang tersimpan otomatis dinilai.
     */
    public function closeIfExpired(): bool
    {
        if ($this->submitted_at !== null || ! $this->isPastSubmitWindow()) {
            return false;
        }

        $this->finalize(null, autoClosed: true);

        return true;
    }

    /**
     * Simpan jawaban sementara (belum dinilai) selama quiz berjalan.
     *
     * @param  array<int|string, mixed>  $answers  question_id => jawaban
     */
    public function saveDraft(array $answers): void
    {
        $questionIds = $this->quiz->questions()->pluck('id')->all();
        $now = now();
        $rows = collect($answers)
            ->filter(fn ($answer, $questionId): bool => in_array((int) $questionId, $questionIds, true))
            ->map(function ($answer, $questionId) use ($now): array {
                $answer = self::normalizeAnswer($answer);

                return [
                    'attempt_id' => $this->id,
                    'question_id' => (int) $questionId,
                    'answer' => $answer === null ? null : json_encode($answer),
                    'point' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->all();

        if ($rows !== []) {
            QuizAnswer::upsert($rows, ['attempt_id', 'question_id'], ['answer', 'point', 'updated_at']);
        }
    }

    /**
     * Nilai dan kunci attempt. Tanpa $answers, yang dinilai adalah jawaban tersimpan otomatis.
     *
     * @param  array<int|string, mixed>|null  $answers  question_id => jawaban
     */
    public function finalize(?array $answers = null, bool $autoClosed = false): void
    {
        $questions = $this->quiz->questions()->get();
        $answers ??= $this->answers()->get()
            ->mapWithKeys(fn (QuizAnswer $answer): array => [$answer->question_id => $answer->answer])
            ->all();

        DB::transaction(function () use ($questions, $answers, $autoClosed): void {
            $score = 0.0;
            $now = now();
            $rows = [];

            foreach ($questions as $question) {
                $answer = self::normalizeAnswer($answers[$question->id] ?? $answers[(string) $question->id] ?? null);
                $point = $question->pointFor($question->question_type === 'multiple_choice' ? ($answer ?? []) : ($answer[0] ?? null));
                $score += $point ?? 0;
                $rows[] = [
                    'attempt_id' => $this->id,
                    'question_id' => $question->id,
                    'answer' => $answer === null ? null : json_encode($answer),
                    'point' => $point,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                QuizAnswer::upsert($rows, ['attempt_id', 'question_id'], ['answer', 'point', 'updated_at']);
            }

            $this->update([
                'submitted_at' => $autoClosed ? ($this->deadline() ?? $now) : $now,
                'score' => $score,
                'auto_closed' => $autoClosed,
            ]);
        });
    }

    /**
     * Jawaban disimpan sebagai daftar: pilihan tunggal/esai menjadi [teks], kosong menjadi null.
     *
     * @return list<mixed>|null
     */
    private static function normalizeAnswer(mixed $answer): ?array
    {
        if ($answer === null || $answer === '') {
            return null;
        }

        return is_array($answer) ? array_values($answer) : [$answer];
    }
}
