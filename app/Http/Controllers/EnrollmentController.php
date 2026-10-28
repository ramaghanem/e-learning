<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{

    public function index(Request $request)
    {
        $tab = in_array($request->input('tab'), ['in-progress', 'completed'], true)
            ? $request->input('tab')
            : 'all';

        $student = auth()->user();

        // Work out the progress from the lessons the student actually completed.
        // This also picks up lessons the instructor added after the student finished.
        $lessonStats = Enrollment::syncProgressFor($student);

        $enrollmentsQuery = fn () => $student->enrollments()
            ->whereHas('course', function ($query) {
                $query->where('status', 'published');
            });

        $counts = [
            'all' => $enrollmentsQuery()->count(),
            'in-progress' => $enrollmentsQuery()->where('progress', '<', 100)->count(),
            'completed' => $enrollmentsQuery()->where('progress', 100)->count(),
        ];

        $enrollments = $enrollmentsQuery()
            ->with(['course.category', 'course.instructor'])
            ->when($tab === 'in-progress', fn ($query) => $query->where('progress', '<', 100))
            ->when($tab === 'completed', fn ($query) => $query->where('progress', 100))
            ->latest('enrolled_at')
            ->paginate(12)
            ->withQueryString();

        return view('student.courses.index', compact('enrollments', 'tab', 'counts', 'lessonStats'));
    }


    public function store(Course $course)
    {
        // Guests go to the login page and come back to this course afterwards.
        if (! auth()->check()) {
            redirect()->setIntendedUrl(route('courses.show', $course));

            return redirect()
                ->route('login')
                ->with('error', 'Please login to enroll');
        }

        abort_unless($course->status === 'published', 404);

        if (auth()->user()->role !== 'student') {
            return back()->with('error', 'Only students can enroll in courses.');
        }


        // createOrFirst() relies on the unique (student_id, course_id) index,
        // so clicking twice never creates a second enrollment.
        $enrollment = Enrollment::createOrFirst(
            [
                'student_id' => auth()->id(),
                'course_id' => $course->id,
            ],
            [
                'enrolled_at' => now(),
                'progress' => 0,
                'status' => 'active',
            ]
        );

        if (! $enrollment->wasRecentlyCreated) {
            return redirect()
                ->route('courses.learn', $course)
                ->with('success', 'You are already enrolled in this course.');
        }

        return redirect()
            ->route('courses.learn', $course)
            ->with('success', "Successfully enrolled in {$course->title}!");
    }
}
