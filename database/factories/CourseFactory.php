<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        $categoryId = Category::query()->inRandomOrder()->value('id');
        $instructorId = User::query()
            ->where('role', 'instructor')
            ->inRandomOrder()
            ->value('id');

        if (! $categoryId) {
            throw new \RuntimeException(
                'Create at least one category before seeding courses.'
            );
        }

        if (! $instructorId) {
            throw new \RuntimeException(
                'Create at least one instructor before seeding courses.'
            );
        }

        return [
            'title' => fake()->unique()->sentence(4),
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraphs(3, true),
            'category_id' => $categoryId,
            'instructor_id' => $instructorId,
            'difficulty' => fake()->randomElement([
                'beginner',
                'intermediate',
                'advanced',
            ]),
            'language' => 'English',
            'thumbnail' => null,
            'learning_objectives' => [
                fake()->sentence(),
                fake()->sentence(),
                fake()->sentence(),
            ],
            'requirements' => fake()->sentence(),
            'target_audience' => fake()->sentence(),
            'duration_hours' => fake()->numberBetween(0, 20),
            'duration_minutes' => fake()->randomElement([0, 15, 30, 45]),
            'price' => fake()->randomElement([
                0, 0, 0, 9.99, 19.99, 29.99, 49.99,
            ]),
            'status' => 'published',
        ];
    }
}
