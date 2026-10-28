<?php

namespace App\Models;

use App\Models\LessonResource;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Section;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable([
    'section_id',
    'title',
    'type',
    'order',
    'video_url',
    'video_path',
    'video_thumbnail',
    'duration_minutes',
    'content',
    'reading_time_minutes',
    'quiz_time_limit_minutes',
    'quiz_passing_score',
    'quiz_max_attempts',
    'quiz_show_review',
    'is_free_preview',
])]
class Lesson extends Model
{
use HasFactory;
    protected function casts(): array
    {
        return [
            'is_free_preview' => 'boolean',
            'quiz_time_limit_minutes' => 'integer',
            'quiz_passing_score' => 'integer',
            'quiz_max_attempts' => 'integer',
            'quiz_show_review' => 'boolean',
        ];
    }

    /**
     * Turns a YouTube or Vimeo link into an embeddable player URL.
     * Returns null for any other link.
     */
    public static function embedUrlFor(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $youtube = '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/)|youtube-nocookie\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})~i';

        if (preg_match($youtube, $url, $match)) {
            return 'https://www.youtube-nocookie.com/embed/' . $match[1];
        }

        $vimeo = '~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/|(?:channels|groups)/[^/]+/(?:videos/)?)?(\d+)(?:/([A-Za-z0-9]+))?~i';

        if (preg_match($vimeo, $url, $match)) {
            // Unlisted Vimeo videos have a privacy hash after the id.
            return 'https://player.vimeo.com/video/' . $match[1]
                . (! empty($match[2]) ? '?h=' . $match[2] : '');
        }

        return null;
    }

    public function videoEmbedUrl(): ?string
    {
        return static::embedUrlFor($this->video_url);
    }

    /**
     * How long a lesson takes, in minutes: the video duration or the article reading time.
     * The same rule in SQL, to add up many lessons in one query.
     */
    public const DURATION_SQL = "case lessons.type when 'video' then coalesce(lessons.duration_minutes, 0) when 'article' then coalesce(lessons.reading_time_minutes, 0) else 0 end";

    public function durationMinutes(): int
    {
        return (int) match ($this->type) {
            'video' => $this->duration_minutes,
            'article' => $this->reading_time_minutes,
            default => 0,
        };
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class);
    }


    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }


    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function quizAttemptsLeft(int $used): ?int
    {
        if ($this->quiz_max_attempts === null) {
            return null;
        }

        return max(0, $this->quiz_max_attempts - $used);
    }
}
