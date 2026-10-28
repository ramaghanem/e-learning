<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizQuestionModelTest extends TestCase
{
    use RefreshDatabase;

    private Lesson $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        // CourseFactory picks an existing category and instructor.
        Category::create(['name' => 'Web']);
        User::factory()->create()->forceFill(['role' => 'instructor'])->save();

        $this->quiz = Lesson::factory()->create(['type' => 'quiz', 'content' => null]);
    }

    public function test_a_question_defaults_to_mcq_with_one_point(): void
    {
        $question = $this->quiz->questions()->create(['question' => '<p>What is 2 + 2?</p>']);

        $question->refresh();

        $this->assertSame(QuizQuestion::TYPE_MCQ, $question->type);
        $this->assertSame('mcq', $question->type);
        $this->assertSame(1, $question->points);
        $this->assertSame(['mcq'], array_keys(QuizQuestion::TYPES));
    }

    public function test_questions_are_listed_in_order_and_points_add_up(): void
    {
        $second = $this->quiz->questions()->create(['question' => '<p>Second</p>', 'order' => 2]);
        $first = $this->quiz->questions()->create(['question' => '<p>First</p>', 'points' => 3, 'order' => 1]);

        $this->assertSame([$first->id, $second->id], $this->quiz->questions->pluck('id')->all());
        $this->assertSame(4, (int) $this->quiz->questions()->sum('points'));
        $this->assertTrue($first->lesson->is($this->quiz));
    }

    public function test_options_are_ordered_and_mark_the_correct_answer(): void
    {
        $question = $this->quiz->questions()->create(['question' => '<p>Pick A</p>']);

        $question->options()->create(['text' => 'B', 'order' => 2]);
        $question->options()->create(['text' => 'A', 'is_correct' => true, 'order' => 1]);

        $options = $question->options;

        $this->assertSame(['A', 'B'], $options->pluck('text')->all());
        $this->assertTrue($options[0]->is_correct);
        $this->assertFalse($options[1]->is_correct);
        $this->assertTrue($options[0]->question->is($question));
    }

    public function test_deleting_the_quiz_lesson_deletes_its_questions_and_options(): void
    {
        $question = $this->quiz->questions()->create(['question' => '<p>Q</p>']);
        $question->options()->create(['text' => 'A', 'is_correct' => true, 'order' => 1]);
        $question->options()->create(['text' => 'B', 'order' => 2]);

        $this->quiz->delete();

        $this->assertDatabaseMissing('quiz_questions', ['id' => $question->id]);
        $this->assertSame(0, QuizQuestionOption::count());
    }
}
