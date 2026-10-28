<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class CertificateController extends Controller
{

    public function show(Course $course)
    {
        $student = auth()->user();
        $enrollment = $course->enrollmentFor($student);

        abort_unless($enrollment, 403);

        Enrollment::syncProgressFor($student, $course->id);
        $enrollment->refresh();

        abort_unless($enrollment->isCompleted(), 403);

        $course->load('instructor');

        return Pdf::loadView('certificates.pdf', compact('course', 'enrollment', 'student'))
            ->setPaper('a4', 'landscape')
            // Embed only the letters used, so the file stays small.
            ->setOption('isFontSubsettingEnabled', true)
            ->download($this->fileName($course));
    }

    
    private function fileName(Course $course): string
    {
        $name = Str::of($course->title)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '-')
            ->limit(80, '')
            ->trim('-')
            ->toString();

        return 'LearnHub-Certificate-' . ($name !== '' ? $name : 'Course') . '.pdf';
    }
}
