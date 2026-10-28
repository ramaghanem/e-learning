<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'student_id',
    'course_id',
    'enrolled_at',
    'progress',
    'status',
    'completed_at',
    'last_accessed_at',
    'certificate_code',
])]
class Enrollment extends Model
{
    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isCompleted(): bool
    {
        return $this->progress === 100;
    }


    public static function syncProgressFor(User $student, ?int $courseId = null): Collection
    {
        $enrollments = $student->enrollments()
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->get();

        if ($enrollments->isEmpty()) {
            return collect();
        }

        $courseIds = $enrollments->pluck('course_id');

        $totals = DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->whereIn('sections.course_id', $courseIds)
            ->groupBy('sections.course_id')
            ->selectRaw('sections.course_id, count(*) as total')
            ->pluck('total', 'course_id');

        $completed = DB::table('lesson_completions')
            ->join('lessons', 'lessons.id', '=', 'lesson_completions.lesson_id')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('lesson_completions.student_id', $student->id)
            ->whereIn('sections.course_id', $courseIds)
            ->groupBy('sections.course_id')
            ->selectRaw('sections.course_id, count(*) as completed')
            ->pluck('completed', 'course_id');

        $stats = collect();

        foreach ($enrollments as $enrollment) {
            $total = (int) ($totals[$enrollment->course_id] ?? 0);
            $done = min((int) ($completed[$enrollment->course_id] ?? 0), $total);

            static::applyProgress($enrollment, $done, $total);

            $stats[$enrollment->course_id] = ['completed' => $done, 'total' => $total];
        }

        return $stats;
    }

    /**
     * Same as syncProgressFor(), but for every student of the given courses
     * (used by the instructor's analytics). 3 queries in total.
     */
    public static function syncProgressForCourses(iterable $courseIds): void
    {
        $courseIds = collect($courseIds);

        $enrollments = static::whereIn('course_id', $courseIds)->get();

        if ($enrollments->isEmpty()) {
            return;
        }

        $totals = DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->whereIn('sections.course_id', $courseIds)
            ->groupBy('sections.course_id')
            ->selectRaw('sections.course_id, count(*) as total')
            ->pluck('total', 'course_id');

        // Completed lessons per enrollment (student + course).
        $completed = DB::table('lesson_completions')
            ->join('lessons', 'lessons.id', '=', 'lesson_completions.lesson_id')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->join('enrollments', function ($join) {
                $join->on('enrollments.student_id', '=', 'lesson_completions.student_id')
                    ->on('enrollments.course_id', '=', 'sections.course_id');
            })
            ->whereIn('sections.course_id', $courseIds)
            ->groupBy('enrollments.id')
            ->selectRaw('enrollments.id as enrollment_id, count(*) as completed')
            ->pluck('completed', 'enrollment_id');

        foreach ($enrollments as $enrollment) {
            $total = (int) ($totals[$enrollment->course_id] ?? 0);
            $done = min((int) ($completed[$enrollment->id] ?? 0), $total);

            static::applyProgress($enrollment, $done, $total);
        }
    }

    /**
     * Saves progress, completion date and certificate code from the lesson counts.
     */
    private static function applyProgress(Enrollment $enrollment, int $done, int $total): void
    {
        // A course without lessons stays at 0% (no division by zero).
        $isCompleted = $total > 0 && $done === $total;

        // Rounded down, so 100% only means every lesson is completed.
        $enrollment->progress = $total > 0 ? intdiv($done * 100, $total) : 0;

        
        $enrollment->status = $isCompleted ? 'completed' : 'active';
        $enrollment->completed_at = $isCompleted ? ($enrollment->completed_at ?? now()) : null;

        if ($isCompleted && ! $enrollment->certificate_code) {
            $enrollment->certificate_code = static::newCertificateCode();
        }

        if ($enrollment->isDirty()) {
            $enrollment->save();
        }
    }

    /**
     * The student's enrollments in published courses, with up-to-date progress
     */
    public static function publishedFor(User $student): EloquentCollection
    {
        static::syncProgressFor($student);

        return $student->enrollments()
            ->whereHas('course', function ($query) {
                $query->where('status', 'published');
            })
            ->with(['course.category', 'course.instructor'])
            ->latest('enrolled_at')
            ->get();
    }

    /**
     * The numbers shown on the student dashboard and the statistics page.
     * Pass enrollments with up-to-date progress .
     */
    public static function summaryOf(Collection $enrollments): array
    {
        $completed = $enrollments->filter->isCompleted();

        return [
            'enrolled' => $enrollments->count(),
            'in_progress' => $enrollments->count() - $completed->count(),
            'completed' => $completed->count(),
            // Every completed course has a downloadable certificate.
            'certificates' => $completed->whereNotNull('certificate_code')->count(),
        ];
    }

    private static function newCertificateCode(): string
    {
        do {
            $code = 'LH-' . strtoupper(Str::random(10));
        } while (static::where('certificate_code', $code)->exists());

        return $code;
    }
}
