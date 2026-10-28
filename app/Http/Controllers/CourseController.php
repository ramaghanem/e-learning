<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\QuizAttempt;


class CourseController extends Controller
{

public function index(Request $request)
{
    $categories = Category::withCount([
        'courses' => function ($query) {
            $query->where('status', 'published');
        },
    ])
        ->orderBy('name')
        ->get();

    $query = Course::with(['category', 'instructor'])
        ->where('status', 'published');


    if ($request->filled('search')) {
        $search = $request->input('search');

        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('short_description', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('instructor', function ($instructorQuery) use ($search) {
                    $instructorQuery->where('name', 'like', "%{$search}%");
                });
        });
    }


    if ($request->filled('category')) {
        $categoryIds = array_filter(
            (array) $request->input('category'),
            fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false
        );

        if (! empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }
    }

    $difficulties = array_intersect(
        (array) $request->input('difficulty', []),
        ['beginner', 'intermediate', 'advanced']
    );

    if (! empty($difficulties)) {
        $query->whereIn('difficulty', $difficulties);
    }

    if ($request->boolean('free_only')) {
        $query->where('price', 0);
    }

    $highestPrice = (float) Course::where('status', 'published')->max('price');
    $priceCeiling = $highestPrice > 0 ? (int) (ceil($highestPrice / 10) * 10) : 100;

    $maxPrice = $request->input('max_price');

    if (is_numeric($maxPrice) && $maxPrice >= 0 && $maxPrice < $priceCeiling) {
        $query->where('price', '<=', $maxPrice);
    }

    $courses = $query
        ->latest()
        ->paginate(12)
        ->withQueryString();

    return view('courses.index', compact('categories', 'courses', 'priceCeiling'));
}


    public function show(Course $course)
    {

        abort_unless($course->status === 'published', 404);

        $course->load(['category', 'instructor', 'sections.lessons.resources'])
            ->loadCount('enrollments');

        $isEnrolled = (bool) $course->enrollmentFor(auth()->user());

        return view('courses.show', compact('course', 'isEnrolled'));
    }

    public function learn(Course $course, ?Lesson $lesson = null)
    {
        abort_unless($course->status === 'published', 404);

        $course->load(['category', 'instructor', 'sections.lessons.resources']);

        $enrollment = $course->enrollmentFor(auth()->user());
        $isEnrolled = (bool) $enrollment;

        $lessons = $course->sections->flatMap->lessons->values();
        $completedLessonIds = collect();

        if ($isEnrolled) {

            Enrollment::syncProgressFor(auth()->user(), $course->id);
            $enrollment->refresh();

            $completedLessonIds = auth()->user()
                ->completedLessons()
                ->whereIn('lessons.id', $lessons->pluck('id'))
                ->pluck('lessons.id');
        }

        if ($lesson) {

            abort_unless($lessons->contains('id', $lesson->id), 404);
        } elseif ($isEnrolled) {

            $lesson = $lessons->first(fn ($item) => ! $completedLessonIds->contains($item->id))
                ?? $lessons->first();
        } else {
            $lesson = $lessons->first(fn ($item) => $item->is_free_preview);
        }

        if (! $isEnrolled && (! $lesson || ! $lesson->is_free_preview)) {
            return redirect()
                ->route('courses.show', $course)
                ->with('error', 'Enroll in this course to open its lessons.');
        }

        if ($isEnrolled) {
            $enrollment->update(['last_accessed_at' => now()]);
        }

        // Quiz start page: the quiz details and the student's attempts.
        $quizAttempts = collect();
        $openAttempt = null;

        if ($lesson->type === 'quiz') {
            $lesson->loadCount('questions')->loadSum('questions', 'points');

            if ($isEnrolled) {
               
                $openAttempt = QuizAttempt::openAttemptFor($lesson, auth()->user());

                $quizAttempts = $lesson->quizAttempts()
                    ->where('student_id', auth()->id())
                    ->orderBy('attempt_number')
                    ->get();
            }
        }

        return view('courses.learn', compact(
            'course', 'lesson', 'isEnrolled', 'enrollment', 'completedLessonIds', 'quizAttempts', 'openAttempt'
        ));
    }
}
