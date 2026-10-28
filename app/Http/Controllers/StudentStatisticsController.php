<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

class StudentStatisticsController extends Controller
{

    public function show()
    {
        $student = auth()->user();


        $summary = Enrollment::summaryOf(Enrollment::publishedFor($student));

        $completedLessons = fn () => $student->completedLessons()
            ->whereHas('section.course', function ($query) {
                $query->where('status', 'published');
            });

        // Total learning time: the durations of the completed lessons.
        $learningMinutes = (int) $completedLessons()->sum(DB::raw(Lesson::DURATION_SQL));

        // Recent activity: the last 10 lessons the student completed.
        $recentLessons = $completedLessons()
            ->with('section.course')
            ->orderByPivot('created_at', 'desc')
            ->orderBy('lessons.id', 'desc')
            ->take(10)
            ->get();

        return view('student.statistics', compact('summary', 'learningMinutes', 'recentLessons'));
    }
}
