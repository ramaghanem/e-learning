<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonQuizSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;
    private Course $course;
    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole('admin');
        $this->instructor = $this->userWithRole('instructor');

        $category = Category::create(['name' => 'Web']);
        $this->course = Course::create([
            'title' => 'Course A',
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => $category->id,
            'instructor_id' => $this->instructor->id,
            'difficulty' => 'beginner',
        ]);
        $this->section = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);
    }

    public function test_a_new_quiz_lesson_gets_the_default_settings(): void
    {
        $lesson = $this->section->lessons()->create(['title' => 'Quiz', 'type' => 'quiz', 'order' => 1])->refresh();

        $this->assertSame(70, $lesson->quiz_passing_score);
        $this->assertNull($lesson->quiz_time_limit_minutes);
        $this->assertNull($lesson->quiz_max_attempts);
        $this->assertTrue($lesson->quiz_show_review);
    }

    public function test_the_lesson_form_shows_the_quiz_settings_with_defaults(): void
    {
        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.create', $this->course))
            ->assertOk()
            ->assertSee('data-lesson-type="quiz"', false)
            ->assertSee('name="quiz_instructions"', false)
            ->assertSee('name="quiz_passing_score"', false)
            ->assertSee('value="70"', false)
            ->assertSee('name="quiz_time_limit_minutes"', false)
            ->assertSee('name="quiz_max_attempts"', false)
            ->assertSee('name="quiz_show_review"', false)
            ->assertSee('Leave empty for unlimited attempts.');
    }

    public function test_instructor_can_create_a_quiz_lesson_with_settings(): void
    {
        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.lessons.store', $this->course), $this->quizData([
                'quiz_instructions' => "Read each question carefully.\nGood luck!",
                'quiz_time_limit_minutes' => 15,
                'quiz_passing_score' => 80,
                'quiz_max_attempts' => 3,
                'quiz_show_review' => '1',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('instructor.courses.content', $this->course));

        $lesson = Lesson::where('type', 'quiz')->sole();

        $this->assertSame("Read each question carefully.\nGood luck!", $lesson->content);
        $this->assertSame(15, $lesson->quiz_time_limit_minutes);
        $this->assertSame(80, $lesson->quiz_passing_score);
        $this->assertSame(3, $lesson->quiz_max_attempts);
        $this->assertTrue($lesson->quiz_show_review);
    }

    public function test_empty_fields_mean_no_time_limit_unlimited_attempts_and_no_review(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.courses.lessons.store', $this->course), $this->quizData([
                'quiz_instructions' => '',
                'quiz_time_limit_minutes' => '',
                'quiz_max_attempts' => '',
                // An unchecked checkbox is not sent.
            ]))
            ->assertSessionHasNoErrors();

        $lesson = Lesson::where('type', 'quiz')->sole();

        $this->assertNull($lesson->content);
        $this->assertNull($lesson->quiz_time_limit_minutes);
        $this->assertNull($lesson->quiz_max_attempts);
        $this->assertSame(70, $lesson->quiz_passing_score);
        $this->assertFalse($lesson->quiz_show_review);
    }

    public function test_the_edit_form_is_filled_and_the_settings_can_be_changed(): void
    {
        $lesson = $this->section->lessons()->create([
            'title' => 'Quiz',
            'type' => 'quiz',
            'order' => 1,
            'content' => 'Old instructions',
            'quiz_time_limit_minutes' => 20,
            'quiz_passing_score' => 60,
            'quiz_max_attempts' => 2,
            'quiz_show_review' => false,
        ]);

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.edit', [$this->course, $lesson]))
            ->assertOk()
            ->assertSee('Old instructions')
            ->assertSee('value="20"', false)
            ->assertSee('value="60"', false)
            ->assertSee('value="2"', false);

        $this->actingAs($this->instructor)
            ->patch(route('instructor.courses.lessons.update', [$this->course, $lesson]), $this->quizData([
                'quiz_instructions' => 'New instructions',
                'quiz_time_limit_minutes' => '',
                'quiz_passing_score' => 90,
                'quiz_max_attempts' => '',
                'quiz_show_review' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $lesson->refresh();

        $this->assertSame('New instructions', $lesson->content);
        $this->assertNull($lesson->quiz_time_limit_minutes);
        $this->assertSame(90, $lesson->quiz_passing_score);
        $this->assertNull($lesson->quiz_max_attempts);
        $this->assertTrue($lesson->quiz_show_review);
    }

    public function test_invalid_quiz_settings_are_rejected(): void
    {
        foreach ([
            ['quiz_passing_score', ['quiz_passing_score' => '']],
            ['quiz_passing_score', ['quiz_passing_score' => 0]],
            ['quiz_passing_score', ['quiz_passing_score' => 101]],
            ['quiz_time_limit_minutes', ['quiz_time_limit_minutes' => 0]],
            ['quiz_time_limit_minutes', ['quiz_time_limit_minutes' => 'abc']],
            ['quiz_max_attempts', ['quiz_max_attempts' => 0]],
            ['quiz_instructions', ['quiz_instructions' => str_repeat('a', 5001)]],
        ] as [$field, $overrides]) {
            $this->actingAs($this->admin)
                ->post(route('admin.courses.lessons.store', $this->course), $this->quizData($overrides))
                ->assertSessionHasErrors($field);
        }

        $this->assertSame(0, Lesson::count());
    }

    public function test_quiz_settings_do_not_affect_other_lesson_types(): void
    {
        // The hidden quiz block is still sent with the form, even with odd values.
        $this->actingAs($this->admin)
            ->post(route('admin.courses.lessons.store', $this->course), [
                'section_id' => $this->section->id,
                'title' => 'Reading',
                'type' => 'article',
                'content' => 'Article text',
                'quiz_instructions' => 'Should be ignored',
                'quiz_passing_score' => 0,
                'quiz_time_limit_minutes' => 30,
                'quiz_max_attempts' => 5,
            ])
            ->assertSessionHasNoErrors();

        $lesson = Lesson::sole()->refresh();

        $this->assertSame('Article text', $lesson->content);
        $this->assertSame(70, $lesson->quiz_passing_score);
        $this->assertNull($lesson->quiz_time_limit_minutes);
        $this->assertNull($lesson->quiz_max_attempts);
    }

    public function test_the_article_content_field_does_not_overwrite_the_quiz_instructions(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.courses.lessons.store', $this->course), $this->quizData([
                'content' => 'Text left in the hidden article field',
                'quiz_instructions' => 'Quiz instructions',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Quiz instructions', Lesson::sole()->content);
    }

    private function quizData(array $overrides = []): array
    {
        return array_merge([
            'section_id' => $this->section->id,
            'title' => 'Quiz 1',
            'type' => 'quiz',
            'quiz_passing_score' => 70,
        ], $overrides);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
