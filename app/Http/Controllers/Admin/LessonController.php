<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CourseLessonController;
use App\Models\Course;

class LessonController extends CourseLessonController
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
