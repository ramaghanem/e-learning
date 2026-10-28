<?php

namespace App\Models;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\QuizAttemptAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'lesson_id',
    'student_id',
    'attempt_number',
    'time_limit_minutes',
    'passing_score',
    'total_points',
    'started_at',
    'expires_at',
    'submitted_at',
    'timed_out',
    'score',
    'percentage',
    'passed',
])]
class QuizAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'time_limit_minutes' => 'integer',
            'passing_score' => 'integer',
            'total_points' => 'integer',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'timed_out' => 'boolean',
            'score' => 'integer',
            'percentage' => 'float',
            'passed' => 'boolean',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class)->orderBy('position');
    }

    public static function start(Lesson $lesson, User $student): self
    {
        return DB::transaction(function () use ($lesson, $student) {
            $questions = $lesson->questions()->with('options')->get();

            $number = 1 + (int) static::query()
                ->where('lesson_id', $lesson->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->max('attempt_number');

            $startedAt = now();
            $timeLimit = $lesson->quiz_time_limit_minutes;

            $attempt = static::create([
                'lesson_id' => $lesson->id,
                'student_id' => $student->id,
                'attempt_number' => $number,
                'time_limit_minutes' => $timeLimit,
                'passing_score' => $lesson->quiz_passing_score ?? 70,
                'total_points' => (int) $questions->sum('points'),
                'started_at' => $startedAt,
                'expires_at' => $timeLimit ? $startedAt->copy()->addMinutes($timeLimit) : null,
            ]);

            foreach ($questions->values() as $index => $question) {
                $attempt->answers()->create([
                    'quiz_question_id' => $question->id,
                    'position' => $index + 1,
                    'question' => $question->question,
                    'points' => $question->points,
                    'options' => $question->options
                        ->map(fn ($option) => ['id' => $option->id, 'text' => $option->text])
                        ->values()
                        ->all(),
                    'correct_option_id' => (int) $question->options->firstWhere('is_correct', true)?->id,
                ]);
            }

            return $attempt;
        });
    }


    public static function openAttemptFor(Lesson $lesson, User $student): ?self
    {
        $attempt = static::query()
            ->where('lesson_id', $lesson->id)
            ->where('student_id', $student->id)
            ->whereNull('submitted_at')
            ->latest('attempt_number')
            ->first();

        if ($attempt && $attempt->hasExpired()) {
            $attempt->grade(timedOut: true);

            return null;
        }

        return $attempt;
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isInProgress(): bool
    {
        return ! $this->isSubmitted();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && now()->greaterThanOrEqualTo($this->expires_at);
    }

    /**
     * Answers sent just before the time ran out can arrive a little late
     * (network delay), so they are still accepted for a few seconds.
     */
    public const GRACE_SECONDS = 5;

    /**
     * True while answers can still be saved: in progress, and the time
     * (plus the grace seconds) has not run out.
     */
    public function acceptsAnswers(): bool
    {
        return $this->isInProgress()
            && ($this->expires_at === null
                || now()->lessThan($this->expires_at->copy()->addSeconds(self::GRACE_SECONDS)));
    }

    /**
     * Milliseconds left before the time runs out, measured on the server.
     * Null when the quiz has no time limit.
     */
    public function remainingMilliseconds(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return max(0, $this->expires_at->getTimestampMs() - now()->getTimestampMs());
    }

    /**
     * The percentage without trailing zeros: "75%", "66.67%", "100%".
     */
    public function percentageLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->percentage, 2), '0'), '.') . '%';
    }

    /**
     * How long the student took, e.g. "less than a minute" or "12 minutes".
     */
    public function durationLabel(): string
    {
        if (! $this->isSubmitted()) {
            return '';
        }

        $minutes = (int) round($this->started_at->diffInSeconds($this->submitted_at, true) / 60);

        return $minutes < 1 ? 'less than a minute' : $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
    }


    public function grade(bool $timedOut = false): void
    {
        DB::transaction(function () use ($timedOut) {
            // Lock the row so two submits at the same time grade it only once.
            $locked = static::query()->whereKey($this->id)->lockForUpdate()->first();

            if ($locked->isSubmitted()) {
                return;
            }

            $score = 0;

            foreach ($this->answers()->get() as $answer) {
                $isCorrect = $answer->selected_option_id !== null
                    && (int) $answer->selected_option_id === (int) $answer->correct_option_id;
                $earned = $isCorrect ? $answer->points : 0;

                $answer->update(['is_correct' => $isCorrect, 'points_earned' => $earned]);
                $score += $earned;
            }

            $total = $locked->total_points;

            $locked->update([
                'score' => $score,
                'percentage' => $total > 0 ? round($score * 100 / $total, 2) : 0,
                // Compared without rounding: score / total >= passing score %.
                'passed' => $total > 0 && $score * 100 >= $locked->passing_score * $total,

                'submitted_at' => $timedOut && $locked->expires_at && $locked->expires_at->isPast()
                    ? $locked->expires_at
                    : now(),
                'timed_out' => $timedOut,
            ]);

            
            if ($locked->passed) {
                $student = $locked->student;

                $student->completedLessons()->syncWithoutDetaching([$locked->lesson_id]);
                Enrollment::syncProgressFor($student, $locked->lesson->section->course_id);
            }
        });

        $this->refresh();
    }
}
