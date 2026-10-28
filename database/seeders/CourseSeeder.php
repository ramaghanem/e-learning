<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        // Add sections and lessons to existing test courses.
        Course::where('title', 'like', '%')
            ->whereDoesntHave('sections.lessons')
            ->each(function (Course $course) {
                $section = $course->sections()->create([
                    'title' => 'Getting Started',
                    'description' => 'Introduction to the course.',
                    'order' => 1,
                ]);

                $section->lessons()->create([
                    'title' => 'Introduction',
                    'type' => 'article',
                    'order' => 1,
                    'content' => 'Welcome to this course. This lesson introduces the main topics.',
                    'reading_time_minutes' => 5,
                    'is_free_preview' => true,
                ]);
            });
    }
}
