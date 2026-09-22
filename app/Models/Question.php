<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

class Question extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['question_text', 'question_type', 'question_option', 'points', 'quiz_id'];

    protected function casts(): array
    {
        return [
            'question_option' => 'array',
            'points' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    /**
     * @return list<string>
     */
    public function optionTexts(): array
    {
        return collect($this->question_option ?? [])->pluck('text')->map(fn ($text): string => (string) $text)->values()->all();
    }

    /**
     * Aturan validasi jawaban mahasiswa untuk soal ini, dengan kunci relatif terhadap $key.
     *
     * @return array<string, array<int, mixed>>
     */
    public function answerRules(string $key): array
    {
        return match ($this->question_type) {
            'multiple_choice' => [
                $key => ['nullable', 'array'],
                $key.'.*' => ['string', Rule::in($this->optionTexts())],
            ],
            'single_choice' => [
                $key => ['nullable', 'string', Rule::in($this->optionTexts())],
            ],
            default => [
                $key => ['nullable', 'string', 'max:10000'],
            ],
        };
    }

    /**
     * Poin untuk jawaban yang diberikan; null untuk esai (dinilai manual).
     */
    public function pointFor(mixed $answer): ?float
    {
        if ($this->question_type === 'essay') {
            return null;
        }

        $correct = collect($this->question_option ?? [])
            ->filter(fn (array $option): bool => ($option['is_correct'] ?? false) === true)
            ->pluck('text')
            ->values()
            ->all();
        $submitted = is_array($answer) ? array_values($answer) : [$answer];
        sort($correct);
        sort($submitted);

        return $correct === $submitted ? (float) $this->points : 0.0;
    }
}
