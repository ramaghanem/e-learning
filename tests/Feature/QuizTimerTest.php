<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizTimerTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $course;
    private Lesson $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();

        $this->student = $this->userWithRole('student');
        $instructor = $this->userWithRole('instructor');

        $this->course = Course::create([
            'title' => 'Laravel Basics',
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => Category::create(['name' => 'Web'])->id,
            'instructor_id' => $instructor->id,
            'difficulty' => 'beginner',
            'price' => 0,
            'status' => 'published',
        ]);

        $section = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);
        $this->quiz = $section->lessons()->create([
            'title' => 'Timed Quiz',
            'type' => 'quiz',
            'order' => 1,
            'quiz_time_limit_minutes' => 15,
        ]);

        // 2 questions of 1 point; the first option is the correct one.
        foreach (['<p>Q1</p>', '<p>Q2</p>'] as $order => $text) {
            $question = $this->quiz->questions()->create(['question' => $text, 'points' => 1, 'order' => $order + 1]);
            $question->options()->create(['text' => 'Right', 'is_correct' => true, 'order' => 1]);
            $question->options()->create(['text' => 'Wrong', 'order' => 2]);
        }

        Enrollment::create(['student_id' => $this->student->id, 'course_id' => $this->course->id, 'enrolled_at' => now()]);
    }

    // ---------- Timer on the quiz screen ----------

    public function test_a_timed_quiz_shows_the_countdown(): void
    {
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertOk()
            ->assertSee('role="timer"', false)
            ->assertSee('data-remaining-ms="900000"', false)
            ->assertSee('15:00')
            // The warnings and the time's up message used by the page script.
            ->assertSee('quiz-timer-flash', false)
            ->assertSee('Less than 5 minutes left.')
            ->assertSee('Less than 1 minute left!')
            ->assertSee("Time's up! Submitting your answers", false);
    }

    public function test_the_countdown_continues_from_the_time_left_on_the_server(): void
    {
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        // The student leaves the page and comes back 10.5 minutes later.
        $this->travel(630)->seconds();

        $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertOk()
            ->assertSee('data-remaining-ms="270000"', false)
            ->assertSee('04:30');
    }

    public function test_a_quiz_without_a_time_limit_has_no_timer(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => null]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertOk()
            ->assertDontSee('role="timer"', false)
            ->assertDontSee('data-remaining-ms', false);
    }

    // ---------- Time left and the grace seconds (model) ----------

    public function test_the_time_left_is_counted_on_the_server(): void
    {
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->assertSame(900000, $attempt->remainingMilliseconds());

        $this->travel(14)->minutes();
        $this->assertSame(60000, $attempt->remainingMilliseconds());

        $this->travel(2)->minutes();
        $this->assertSame(0, $attempt->remainingMilliseconds());

        $this->quiz->update(['quiz_time_limit_minutes' => null]);
        $this->assertNull(QuizAttempt::start($this->quiz->refresh(), $this->student)->remainingMilliseconds());
    }

    public function test_answers_are_accepted_until_the_grace_seconds_end(): void
    {
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->travel(15)->minutes();
        $this->assertTrue($attempt->hasExpired());
        $this->assertTrue($attempt->acceptsAnswers());

        $this->travel(QuizAttempt::GRACE_SECONDS)->seconds();
        $this->assertFalse($attempt->acceptsAnswers());
    }

    public function test_a_submitted_attempt_never_accepts_answers(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => null]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->assertTrue($attempt->acceptsAnswers());

        $attempt->grade();
        $this->assertFalse($attempt->acceptsAnswers());
    }

    // ---------- Auto-submit at 0:00 ----------

    public function test_at_zero_the_attempt_is_submitted_with_the_answers_saved_in_time(): void
    {
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        [$first, $second] = $attempt->answers->all();

        // Q1 answered right during the quiz.
        $this->travel(10)->minutes();
        $this->saveAnswer($attempt, $first->id, $first->correct_option_id)->assertOk();

        // Q2 chosen at the last second; the request arrives 2 seconds after 0:00.
        $this->travel(5)->minutes();
        $this->travel(2)->seconds();
        $this->saveAnswer($attempt, $second->id, $second->correct_option_id)->assertOk();

        // The page submits the quiz when its timer reaches 0:00.
        $this->actingAs($this->student)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz, $attempt]))
            ->assertRedirect(route('courses.quizzes.results', [$this->course, $this->quiz, $attempt]))
            ->assertSessionHas('error', "Time's up! Your attempt was submitted.");

        $attempt->refresh();
        $this->assertTrue($attempt->isSubmitted());
        $this->assertTrue($attempt->timed_out);
        $this->assertSame(2, $attempt->score);
        $this->assertTrue($attempt->submitted_at->equalTo($attempt->expires_at));
    }

    public function test_an_answer_arriving_after_the_grace_seconds_is_not_counted(): void
    {
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        $first = $attempt->answers->first();

        $this->travel(15)->minutes();
        $this->travel(QuizAttempt::GRACE_SECONDS + 1)->seconds();

        $this->saveAnswer($attempt, $first->id, $first->correct_option_id)->assertStatus(409);

        $attempt->refresh();
        $this->assertTrue($attempt->timed_out);
        $this->assertSame(0, $attempt->score);
        $this->assertNull($first->fresh()->selected_option_id);
    }

    public function test_coming_back_after_the_time_ran_out_shows_the_result_on_the_start_page(): void
    {
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        $first = $attempt->answers->first();
        $this->saveAnswer($attempt, $first->id, $first->correct_option_id)->assertOk();

        // The student closes the tab and comes back the next day.
        $this->travel(1)->days();

        $this->actingAs($this->student)
            ->get(route('courses.learn', [$this->course, $this->quiz]))
            ->assertOk()
            ->assertDontSee('Resume Quiz')
            ->assertSee("Time's up", false)
            ->assertSee('1 / 2');

        $this->assertTrue($attempt->fresh()->timed_out);
    }

    // ---------- Helpers ----------

    private function attemptUrl(QuizAttempt $attempt): string
    {
        return route('courses.quizzes.attempt', [$this->course, $this->quiz, $attempt]);
    }

    private function saveAnswer(QuizAttempt $attempt, int $answerId, int $optionId)
    {
        return $this->actingAs($this->student)->patchJson(
            route('courses.quizzes.answers', [$this->course, $this->quiz, $attempt]),
            ['answer_id' => $answerId, 'option_id' => $optionId]
        );
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
