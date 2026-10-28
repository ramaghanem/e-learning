<?php

namespace App\Models;

use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quiz_attempt_id',
    'quiz_question_id',
    'position',
    'question',
    'points',
    'options',
    'correct_option_id',
    'selected_option_id',
    'answered_at',
    'is_correct',
    'points_earned',
])]
class QuizAttemptAnswer extends Model
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'points' => 'integer',
            'options' => 'array',
            'correct_option_id' => 'integer',
            'selected_option_id' => 'integer',
            'answered_at' => 'datetime',
            'is_correct' => 'boolean',
            'points_earned' => 'integer',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }


    public function quizQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    public function isAnswered(): bool
    {
        return $this->selected_option_id !== null;
    }

   
    public function hasOption(int $optionId): bool
    {
        return collect($this->options)->contains('id', $optionId);
    }

    public function selectedOptionText(): ?string
    {
        return $this->optionText($this->selected_option_id);
    }

    public function correctOptionText(): ?string
    {
        return $this->optionText($this->correct_option_id);
    }

    private function optionText(?int $optionId): ?string
    {
        if ($optionId === null) {
            return null;
        }

        return collect($this->options)->firstWhere('id', $optionId)['text'] ?? null;
    }
}
