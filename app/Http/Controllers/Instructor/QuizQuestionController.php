<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\CourseQuizQuestionController;
use App\Models\Course;

class QuizQuestionController extends CourseQuizQuestionController
{
    protected function authorizeCourse(Course $course): void
    {
        abort_unless(
            (int) $course->instructor_id === (int) auth()->id(),
            403
        );
    }

    protected function routePrefix(): string
    {
        return 'instructor';
    }
}
