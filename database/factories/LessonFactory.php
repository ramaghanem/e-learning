<?php

namespace Database\Factories;

use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'title' => fake()->sentence(4),
            'type' => 'article',
            'order' => 1,
            'video_url' => null,
            'video_path' => null,
            'video_thumbnail' => null,
            'duration_minutes' => null,
            'content' => fake()->paragraphs(3, true),
            'reading_time_minutes' => fake()->numberBetween(5, 20),
            'is_free_preview' => true,
        ];
    }
}
