<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAttemptModelTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Lesson $quiz;
    private QuizQuestion $q1;   // 3 points: Paris (correct), London
    private QuizQuestion $q2;   // 7 points: One, Two (correct), Three

    protected function setUp(): void
    {
        parent::setUp();

        $instructor = $this->userWithRole('instructor');
        $this->student = $this->userWithRole('student');

        $course = Course::create([
            'title' => 'Course A',
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => Category::create(['name' => 'Web'])->id,
            'instructor_id' => $instructor->id,
            'difficulty' => 'beginner',
        ]);
        $section = Section::create(['course_id' => $course->id, 'title' => 'Intro', 'order' => 1]);

        $this->quiz = $section->lessons()->create(['title' => 'Quiz 1', 'type' => 'quiz', 'order' => 1])->refresh();

        $this->q1 = $this->question('<p>Capital of France?</p>', 3, ['Paris', 'London'], 0, order: 1);
        $this->q2 = $this->question('<p>Pick two</p>', 7, ['One', 'Two', 'Three'], 1, order: 2);
    }

    // ---------- Starting an attempt ----------

    public function test_starting_copies_the_settings_and_every_question(): void
    {
        $this->quiz->update(['quiz_passing_score' => 80, 'quiz_time_limit_minutes' => 15]);
        $this->freezeSecond(); // the database keeps whole seconds

        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame(80, $attempt->passing_score);
        $this->assertSame(15, $attempt->time_limit_minutes);
        $this->assertSame(10, $attempt->total_points);
        $this->assertTrue($attempt->started_at->equalTo(now()));
        $this->assertTrue($attempt->expires_at->equalTo(now()->addMinutes(15)));
        $this->assertTrue($attempt->isInProgress());
        $this->assertFalse($attempt->hasExpired());

        $answers = $attempt->answers;

        $this->assertSame([1, 2], $answers->pluck('position')->all());
        $this->assertSame([$this->q1->id, $this->q2->id], $answers->pluck('quiz_question_id')->all());
        $this->assertSame('<p>Capital of France?</p>', $answers[0]->question);
        $this->assertSame(3, $answers[0]->points);
        $this->assertSame(
            [['id' => $this->q1->options[0]->id, 'text' => 'Paris'], ['id' => $this->q1->options[1]->id, 'text' => 'London']],
            $answers[0]->options
        );
        $this->assertSame($this->q1->options[0]->id, $answers[0]->correct_option_id);
        $this->assertSame($this->q2->options[1]->id, $answers[1]->correct_option_id);
        $this->assertNull($answers[0]->selected_option_id);
        $this->assertNull($answers[0]->is_correct);
    }

    public function test_a_quiz_without_a_time_limit_never_expires(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);

        $this->assertNull($attempt->expires_at);
        $this->travel(10)->years();
        $this->assertFalse($attempt->hasExpired());
    }

    public function test_a_timed_attempt_expires_when_the_time_is_up(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 10]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->travel(9)->minutes();
        $this->assertFalse($attempt->hasExpired());

        $this->travel(1)->minutes();
        $this->assertTrue($attempt->hasExpired());
    }

    public function test_attempt_numbers_count_up_per_student_and_quiz(): void
    {
        $other = $this->userWithRole('student');

        $this->assertSame(1, QuizAttempt::start($this->quiz, $this->student)->attempt_number);
        $this->assertSame(2, QuizAttempt::start($this->quiz, $this->student)->attempt_number);
        $this->assertSame(1, QuizAttempt::start($this->quiz, $other)->attempt_number);

        $this->assertSame(2, $this->student->quizAttempts()->count());
        $this->assertSame(3, $this->quiz->quizAttempts()->count());
    }

    public function test_the_same_attempt_number_cannot_be_saved_twice(): void
    {
        QuizAttempt::start($this->quiz, $this->student);

        $this->expectException(QueryException::class);

        QuizAttempt::create([
            'lesson_id' => $this->quiz->id,
            'student_id' => $this->student->id,
            'attempt_number' => 1,
            'passing_score' => 70,
            'total_points' => 10,
            'started_at' => now(),
        ]);
    }

    public function test_a_question_appears_only_once_per_attempt(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);

        $this->expectException(QueryException::class);

        $attempt->answers()->create([
            'quiz_question_id' => $this->q1->id,
            'position' => 1,
            'question' => 'Again',
            'points' => 1,
            'options' => [],
            'correct_option_id' => 1,
        ]);
    }

    // ---------- Grading ----------

    public function test_grading_scores_correct_wrong_and_unanswered_questions(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, 1, $this->q1->options[0]->id);   // correct, 3 points
        // question 2 not answered

        $attempt->grade();

        $this->assertTrue($attempt->isSubmitted());
        $this->assertFalse($attempt->timed_out);
        $this->assertSame(3, $attempt->score);
        $this->assertSame(30.0, $attempt->percentage);
        $this->assertFalse($attempt->passed);

        $answers = $attempt->answers;
        $this->assertTrue($answers[0]->is_correct);
        $this->assertSame(3, $answers[0]->points_earned);
        $this->assertFalse($answers[1]->is_correct);
        $this->assertSame(0, $answers[1]->points_earned);
        $this->assertFalse($answers[1]->isAnswered());
    }

    public function test_a_wrong_answer_earns_nothing(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, 1, $this->q1->options[1]->id);   // London: wrong
        $this->answer($attempt, 2, $this->q2->options[1]->id);   // Two: correct, 7 points

        $attempt->grade();

        $this->assertSame(7, $attempt->score);
        $this->assertSame(70.0, $attempt->percentage);
        $this->assertSame([false, true], $attempt->answers->pluck('is_correct')->all());
        $this->assertSame('London', $attempt->answers[0]->selectedOptionText());
        $this->assertSame('Paris', $attempt->answers[0]->correctOptionText());
    }

    public function test_the_passing_score_is_reached_exactly_at_the_threshold(): void
    {
        // 7 of 10 points = 70%, and the default passing score is 70%.
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, 2, $this->q2->options[1]->id);
        $attempt->grade();
        $this->assertTrue($attempt->passed);

        // 3 of 10 points = 30%: failed.
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, 1, $this->q1->options[0]->id);
        $attempt->grade();
        $this->assertFalse($attempt->passed);
    }

    public function test_the_percentage_is_not_rounded_up_to_pass(): void
    {
        // 2 of 3 points = 66.67%, with a 67% passing score: failed.
        $this->quiz->questions()->delete();
        $this->quiz->update(['quiz_passing_score' => 67]);
        $a = $this->question('<p>A</p>', 1, ['x', 'y'], 0, order: 1);
        $b = $this->question('<p>B</p>', 1, ['x', 'y'], 0, order: 2);
        $this->question('<p>C</p>', 1, ['x', 'y'], 0, order: 3);

        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        $this->answer($attempt, 1, $a->options[0]->id);
        $this->answer($attempt, 2, $b->options[0]->id);
        $attempt->grade();

        $this->assertSame(2, $attempt->score);
        $this->assertSame(66.67, $attempt->percentage);
        $this->assertFalse($attempt->passed);
    }

    public function test_grading_twice_changes_nothing(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, 1, $this->q1->options[0]->id);
        $attempt->grade();
        $submittedAt = $attempt->submitted_at;

        $this->travel(5)->minutes();
        $this->answer($attempt, 2, $this->q2->options[1]->id);   // too late
        $attempt->grade(timedOut: true);

        $attempt->refresh();
        $this->assertSame(3, $attempt->score);
        $this->assertTrue($attempt->submitted_at->equalTo($submittedAt));
        $this->assertFalse($attempt->timed_out);
    }

    public function test_a_timed_out_attempt_graded_later_ends_at_the_time_limit(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 10]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->travel(2)->hours();
        $attempt->grade(timedOut: true);

        $this->assertTrue($attempt->timed_out);
        $this->assertTrue($attempt->submitted_at->equalTo($attempt->expires_at));
    }

    public function test_an_empty_quiz_scores_zero_and_does_not_pass(): void
    {
        $this->quiz->questions()->delete();

        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $attempt->grade();

        $this->assertSame(0, $attempt->total_points);
        $this->assertSame(0, $attempt->score);
        $this->assertSame(0.0, $attempt->percentage);
        $this->assertFalse($attempt->passed);
    }

    // ---------- Changes to the quiz after the attempt ----------

    public function test_editing_the_quiz_during_an_attempt_does_not_change_it(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $paris = $this->q1->options[0];
        $london = $this->q1->options[1];

        // The instructor changes everything while the student is answering.
        $this->quiz->update(['quiz_passing_score' => 100]);
        $this->q1->update(['question' => '<p>Changed</p>', 'points' => 50]);
        $paris->update(['is_correct' => false, 'text' => 'Paris (edited)']);
        $london->update(['is_correct' => true]);
        $this->q2->delete();
        $this->question('<p>New question</p>', 20, ['a', 'b'], 0, order: 3);

        $this->answer($attempt, 1, $paris->id);
        $attempt->grade();

        // Graded on the copy: Paris was correct, 3 of 10 points, 70% to pass.
        $this->assertSame(3, $attempt->score);
        $this->assertSame(10, $attempt->total_points);
        $this->assertSame(70, $attempt->passing_score);
        $this->assertSame(2, $attempt->answers()->count());
        $this->assertSame('<p>Capital of France?</p>', $attempt->answers[0]->question);
        $this->assertSame('Paris', $attempt->answers[0]->selectedOptionText());
    }

    public function test_past_results_survive_deleting_questions_and_options(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, 1, $this->q1->options[1]->id);   // London (wrong)
        $this->answer($attempt, 2, $this->q2->options[1]->id);   // Two (correct)
        $attempt->grade();

        // Later the instructor deletes the chosen option and a whole question.
        $this->q1->options[1]->delete();
        $this->q2->delete();

        $attempt->refresh();
        $answers = $attempt->answers;

        $this->assertSame(7, $attempt->score);
        $this->assertSame(70.0, $attempt->percentage);
        $this->assertTrue($attempt->passed);
        $this->assertCount(2, $answers);

        $this->assertSame('London', $answers[0]->selectedOptionText());
        $this->assertSame('Paris', $answers[0]->correctOptionText());
        $this->assertFalse($answers[0]->is_correct);

        // The deleted question is still shown from the copy.
        $this->assertNull($answers[1]->quiz_question_id);
        $this->assertNull($answers[1]->quizQuestion);
        $this->assertSame('<p>Pick two</p>', $answers[1]->question);
        $this->assertSame('Two', $answers[1]->selectedOptionText());
        $this->assertTrue($answers[1]->is_correct);
        $this->assertSame(7, $answers[1]->points_earned);
    }

    // ---------- Relations and deleting ----------

    public function test_relations_link_attempts_answers_students_and_questions(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $answer = $attempt->answers->first();

        $this->assertTrue($attempt->lesson->is($this->quiz));
        $this->assertTrue($attempt->student->is($this->student));
        $this->assertTrue($answer->attempt->is($attempt));
        $this->assertTrue($answer->quizQuestion->is($this->q1));
        $this->assertTrue($answer->hasOption($this->q1->options[0]->id));
        $this->assertFalse($answer->hasOption($this->q2->options[0]->id));
    }

    public function test_deleting_the_quiz_lesson_or_the_student_deletes_their_attempts(): void
    {
        $other = $this->userWithRole('student');
        QuizAttempt::start($this->quiz, $this->student);
        QuizAttempt::start($this->quiz, $other);

        $other->delete();
        $this->assertSame(1, QuizAttempt::count());
        $this->assertSame(2, QuizAttemptAnswer::count());

        $this->quiz->delete();
        $this->assertSame(0, QuizAttempt::count());
        $this->assertSame(0, QuizAttemptAnswer::count());
    }

    // ---------- Helpers ----------

    private function question(string $text, int $points, array $options, int $correct, int $order): QuizQuestion
    {
        $question = $this->quiz->questions()->create(['question' => $text, 'points' => $points, 'order' => $order]);

        foreach ($options as $index => $optionText) {
            $question->options()->create([
                'text' => $optionText,
                'is_correct' => $index === $correct,
                'order' => $index + 1,
            ]);
        }

        return $question->load('options');
    }

    private function answer(QuizAttempt $attempt, int $position, int $optionId): void
    {
        $attempt->answers()->where('position', $position)->first()
            ->update(['selected_option_id' => $optionId, 'answered_at' => now()]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
