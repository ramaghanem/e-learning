<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
     public function student(): View
    {
        $student = auth()->user();


        $lessonStats = Enrollment::syncProgressFor($student);

        $enrollments = $student->enrollments()
            ->whereHas('course', function ($query) {
                $query->where('status', 'published');
            })
            ->with(['course.category', 'course.instructor'])
            ->get();

        // Same numbers as the statistics page.
        $summary = Enrollment::summaryOf($enrollments);

        return view('dashboards.student', [
            'enrolledCount' => $summary['enrolled'],
            'inProgressCount' => $summary['in_progress'],
            'completedCount' => $summary['completed'],
            'certificatesCount' => $summary['certificates'],

            'continueLearning' => $enrollments->reject->isCompleted()
                ->sortByDesc(fn ($enrollment) => $enrollment->last_accessed_at ?? $enrollment->enrolled_at)
                ->take(3)
                ->values(),
            'lessonStats' => $lessonStats,
        ]);
    }

    public function instructor(): View
    {
        // Bring every student's progress up to date before counting (3 queries for all courses).
        Enrollment::syncProgressForCourses(
            Course::where('instructor_id', auth()->id())->pluck('id')
        );

        $courses = Course::where('instructor_id', auth()->id())
            ->withCount(Course::analyticsCounts())
            ->withAvg('enrollments', 'progress')
            ->latest()
            ->get();

        return view('dashboards.instructor', [
            'coursesCount' => $courses->count(),
            'studentsCount' => auth()->user()->studentsCount(),
            // No reviews system yet, so there is no rating to show.
            'averageRating' => null,
            'courses' => $courses,
        ]);
    }

    public function admin(): View
    {
        return view('dashboards.admin', [
            'usersCount' => User::count(),
            'coursesCount' => Course::count(),
            'enrollmentsCount' => Enrollment::count(),
            'recentUsers' => User::latest()->take(10)->get(),
            'recentEnrollments' => Enrollment::with(['student', 'course'])
                ->latest('enrolled_at')
                ->take(10)
                ->get(),
        ]);
    }
}
