<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'short_description',
    'description',
    'category_id',
    'instructor_id',
    'difficulty',
    'language',
    'thumbnail',
    'learning_objectives',
    'requirements',
    'target_audience',
    'duration_hours',
    'duration_minutes',
    'price',
    'status',
])]
class Course extends Model
{    use HasFactory;
    protected function casts(): array
    {
        return [
            'learning_objectives' => 'array',
            'price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function sections(): HasMany
{
    return $this->hasMany(Section::class)->orderBy('order');
}

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * A student is "active" when they opened the course in the last 7 days.
     */
    public const ACTIVE_DAYS = 7;

    /**
     * Counts for the instructor's analytics, for withCount() / loadCount():
     * enrollments_count, active_students_count and completed_students_count.
     * Use together with withAvg('enrollments', 'progress').
     */
    public static function analyticsCounts(): array
    {
        return [
            'enrollments',
            'enrollments as active_students_count' => function ($query) {
                $query->where('last_accessed_at', '>=', now()->subDays(self::ACTIVE_DAYS));
            },
            'enrollments as completed_students_count' => function ($query) {
                $query->where('progress', 100);
            },
        ];
    }

    /**
     * Completed students / all enrolled students, in %. Null when nobody is enrolled.
     */
    public function completionRate(): ?int
    {
        if (! $this->enrollments_count) {
            return null;
        }

        return (int) round($this->completed_students_count * 100 / $this->enrollments_count);
    }

    /**
     * Average progress of all enrolled students, in %. Null when nobody is enrolled.
     */
    public function averageProgress(): ?int
    {
        return $this->enrollments_count
            ? (int) round($this->enrollments_avg_progress)
            : null;
    }

    /**
     * The enrollment of this user in the course, or null when they are not enrolled.
     */
    public function enrollmentFor(?User $user): ?Enrollment
    {
        if (! $user) {
            return null;
        }

        return $this->enrollments()
            ->where('student_id', $user->id)
            ->first();
    }
}
