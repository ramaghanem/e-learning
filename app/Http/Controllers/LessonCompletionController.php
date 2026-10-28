<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Http\Request;

class LessonCompletionController extends Controller
{

    public function store(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($course->status === 'published', 404);

       
        abort_unless(
            $lesson->section()->where('course_id', $course->id)->exists(),
            404
        );

        // A quiz lesson is completed by passing the quiz, not by hand.
        if ($lesson->type === 'quiz') {
            $error = 'Pass the quiz to complete this lesson.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $error], 422);
            }

            return redirect()
                ->route('courses.learn', [$course, $lesson])
                ->with('error', $error);
        }

        $student = auth()->user();
        $enrollment = $course->enrollmentFor($student);

        // Only students enrolled in this course can track progress in it.
        if (! $enrollment) {
            $error = 'Enroll in this course to track your progress.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $error], 403);
            }

            return redirect()
                ->route('courses.show', $course)
                ->with('error', $error);
        }

        // The unique (student_id, lesson_id) index prevents duplicates;
        // syncWithoutDetaching() does not insert a lesson that is already there.
        $student->completedLessons()->syncWithoutDetaching([$lesson->id]);

        $stats = Enrollment::syncProgressFor($student, $course->id)[$course->id];
        $courseCompleted = $stats['total'] > 0 && $stats['completed'] === $stats['total'];

        if ($courseCompleted) {
            // Stay on this lesson.
            $message = "Congratulations! You completed {$course->title}. Your certificate is ready.";
            $next = null;
        } else {
            // Go on to the next lesson, if there is one.
            $message = "Lesson completed: {$lesson->title}";

            $lessons = $course->load('sections.lessons')->sections->flatMap->lessons->values();
            $index = $lessons->search(fn ($item) => $item->id === $lesson->id);
            $next = $lessons[$index + 1] ?? null;
        }

        if ($request->expectsJson()) {

            if ($next) {
                session()->flash('success', $message);
            }

            $sectionLessonIds = $lesson->section->lessons()->pluck('id');

            return response()->json([
                'message' => $message,
                'lessonId' => $lesson->id,
                'sectionId' => $lesson->section_id,
                'sectionCompletedLessons' => $student->completedLessons()->whereIn('lessons.id', $sectionLessonIds)->count(),
                'completedLessons' => $stats['completed'],
                'totalLessons' => $stats['total'],
                'progress' => $enrollment->refresh()->progress,
                'courseCompleted' => $courseCompleted,
                'certificateUrl' => $courseCompleted ? route('courses.certificate', $course) : null,
                'nextUrl' => $next ? route('courses.learn', [$course, $next]) : null,
            ]);
        }

        return redirect()
            ->route('courses.learn', [$course, $next ?? $lesson])
            ->with('success', $message);
    }
}
