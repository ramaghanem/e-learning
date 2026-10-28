<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CourseQuizQuestionController;
use App\Models\Course;

class QuizQuestionController extends CourseQuizQuestionController
{
    protected function authorizeCourse(Course $course): void
    {
        // Admin can manage any course.
    }

    protected function routePrefix(): string
    {
        return 'admin';
    }
}
