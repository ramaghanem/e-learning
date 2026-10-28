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

class QuizSubmitTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructor;
    private Course $course;
    private Section $section;
    private Lesson $article;
    private Lesson $quiz;
    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = $this->userWithRole('student');
        $this->instructor = $this->userWithRole('instructor');

        $this->course = Course::create([
            'title' => 'Laravel Basics',
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => Category::create(['name' => 'Web'])->id,
            'instructor_id' => $this->instructor->id,
            'difficulty' => 'beginner',
            'price' => 0,
            'status' => 'published',
        ]);

        // The course: an article, then the quiz (passing score 70%).
        $this->section = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);
        $this->article = $this->section->lessons()->create(['title' => 'Reading', 'type' => 'article', 'order' => 1, 'content' => 'Text']);
        $this->quiz = $this->section->lessons()->create(['title' => 'Chapter Quiz', 'type' => 'quiz', 'order' => 2]);

        // 3 questions: 1, 1 and 2 points. The first option is the correct one.
        foreach ([1, 1, 2] as $order => $points) {
            $question = $this->quiz->questions()->create(['question' => '<p>Q' . ($order + 1) . '</p>', 'points' => $points, 'order' => $order + 1]);
            $question->options()->create(['text' => 'Right', 'is_correct' => true, 'order' => 1]);
            $question->options()->create(['text' => 'Wrong', 'order' => 2]);
        }

        $this->enrollment = Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'enrolled_at' => now(),
        ]);
    }

    // ---------- Quiz screen ----------

    public function test_the_quiz_screen_has_the_submit_button_and_the_confirmation(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);

        $this->actingAs($this->student)
            ->get(route('courses.quizzes.attempt', [$this->course, $this->quiz, $attempt]))
            ->assertOk()
            ->assertSee('action="' . route('courses.quizzes.submit', [$this->course, $this->quiz, $attempt]) . '"', false)
            ->assertSee('form="quiz-submit-form"', false)
            ->assertSee('Submit Quiz')
            ->assertSee('Are you sure you want to submit?')
            ->assertSee('These questions are not answered yet:')
            ->assertSee('Go Back')
            ->assertSee('Submit Anyway');
    }

    // ---------- Grading ----------

    public function test_submitting_grades_the_attempt_with_the_saved_answers(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, [1 => 'right', 2 => 'wrong']);   // question 3 not answered

        $this->submit($attempt)
            ->assertRedirect($this->resultsUrl($attempt))
            ->assertSessionMissing('error');

        $attempt->refresh();
        $this->assertTrue($attempt->isSubmitted());
        $this->assertFalse($attempt->timed_out);
        $this->assertSame(1, $attempt->score);
        $this->assertSame(25.0, $attempt->percentage);
        $this->assertFalse($attempt->passed);
        $this->assertSame([true, false, false], $attempt->answers->pluck('is_correct')->all());
        $this->assertSame([1, 0, 0], $attempt->answers->pluck('points_earned')->all());
    }

    public function test_passing_the_quiz_completes_the_lesson_and_updates_the_progress(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, [1 => 'right', 3 => 'right']);   // 3 of 4 = 75%

        $this->submit($attempt)->assertRedirect($this->resultsUrl($attempt));

        $this->assertTrue($attempt->fresh()->passed);
        $this->assertTrue($this->student->completedLessons()->whereKey($this->quiz->id)->exists());

        // 1 of 2 lessons done.
        $this->enrollment->refresh();
        $this->assertSame(50, $this->enrollment->progress);
        $this->assertNull($this->enrollment->certificate_code);
    }

    public function test_passing_the_last_lesson_completes_the_course_and_gives_the_certificate(): void
    {
        $this->actingAs($this->student)->post(route('courses.lessons.complete', [$this->course, $this->article]));

        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, [1 => 'right', 2 => 'right', 3 => 'right']);
        $this->submit($attempt)->assertRedirect($this->resultsUrl($attempt));

        $this->enrollment->refresh();
        $this->assertSame(100, $this->enrollment->progress);
        $this->assertSame('completed', $this->enrollment->status);
        $this->assertNotNull($this->enrollment->completed_at);
        $this->assertNotNull($this->enrollment->certificate_code);
    }

    public function test_failing_the_quiz_does_not_complete_the_lesson(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, [3 => 'right']);   // 2 of 4 = 50%

        $this->submit($attempt)->assertRedirect($this->resultsUrl($attempt));

        $this->assertFalse($this->student->completedLessons()->whereKey($this->quiz->id)->exists());
        $this->assertSame(0, $this->enrollment->fresh()->progress);
    }

    public function test_a_failed_attempt_after_passing_keeps_the_lesson_completed(): void
    {
        $passed = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($passed, [1 => 'right', 2 => 'right', 3 => 'right']);
        $this->submit($passed);

        $failed = QuizAttempt::start($this->quiz, $this->student);
        $this->submit($failed)->assertRedirect($this->resultsUrl($failed));

        $this->assertFalse($failed->fresh()->passed);
        $this->assertTrue($this->student->completedLessons()->whereKey($this->quiz->id)->exists());
        $this->assertSame(50, $this->enrollment->fresh()->progress);
    }

    public function test_the_lesson_page_shows_the_quiz_lesson_as_completed_after_passing(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, [1 => 'right', 2 => 'right', 3 => 'right']);
        $this->submit($attempt);

        $this->actingAs($this->student)
            ->get(route('courses.learn', [$this->course, $this->quiz]))
            ->assertOk()
            ->assertSee('You passed this quiz.')
            ->assertSee('Passed')
            ->assertDontSee('Mark as Complete');
    }

    public function test_an_attempt_cannot_be_submitted_twice(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->answer($attempt, [1 => 'right']);
        $this->submit($attempt);
        $submittedAt = $attempt->fresh()->submitted_at;

        $this->travel(1)->minutes();
        $attempt->answers()->update(['selected_option_id' => null]);   // even if something changed

        $this->submit($attempt)
            ->assertRedirect($this->resultsUrl($attempt))
            ->assertSessionHas('error', 'This attempt has already been submitted.');

        $attempt->refresh();
        $this->assertSame(1, $attempt->score);
        $this->assertTrue($attempt->submitted_at->equalTo($submittedAt));
    }

    // ---------- Time limit ----------

    public function test_submitting_after_the_time_ran_out_counts_as_times_up(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 10]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        $this->answer($attempt, [1 => 'right', 2 => 'right', 3 => 'right']);

        // The timer reached 0:00 and submitted the quiz.
        $this->travel(10)->minutes();
        $this->travel(1)->seconds();

        $this->submit($attempt)
            ->assertRedirect($this->resultsUrl($attempt))
            ->assertSessionHas('error', "Time's up! Your attempt was submitted.");

        $attempt->refresh();
        $this->assertTrue($attempt->timed_out);
        $this->assertTrue($attempt->submitted_at->equalTo($attempt->expires_at));
        $this->assertTrue($this->student->completedLessons()->whereKey($this->quiz->id)->exists());
    }

    public function test_submitting_in_time_is_not_times_up(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 10]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->travel(9)->minutes();
        $this->submit($attempt);

        $this->assertFalse($attempt->fresh()->timed_out);
    }

    public function test_a_passing_attempt_submitted_on_return_also_completes_the_lesson(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 10]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        $this->answer($attempt, [1 => 'right', 2 => 'right', 3 => 'right']);

        // The student closed the tab; the attempt is graded when they come back.
        $this->travel(1)->days();
        $this->actingAs($this->student)->get(route('courses.learn', [$this->course, $this->quiz]))->assertOk();

        $this->assertTrue($attempt->fresh()->passed);
        $this->assertTrue($this->student->completedLessons()->whereKey($this->quiz->id)->exists());
        $this->assertSame(50, $this->enrollment->fresh()->progress);
    }

    // ---------- Access ----------

    public function test_another_student_cannot_submit_the_attempt(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $other = $this->userWithRole('student');
        Enrollment::create(['student_id' => $other->id, 'course_id' => $this->course->id, 'enrolled_at' => now()]);

        $this->actingAs($other)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz, $attempt]))
            ->assertForbidden();

        $this->assertTrue($attempt->fresh()->isInProgress());
    }

    public function test_the_attempt_must_belong_to_the_quiz_in_the_url(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $otherQuiz = $this->section->lessons()->create(['title' => 'Quiz 2', 'type' => 'quiz', 'order' => 3]);

        $this->actingAs($this->student)
            ->post(route('courses.quizzes.submit', [$this->course, $otherQuiz, $attempt]))
            ->assertNotFound();

        $this->assertTrue($attempt->fresh()->isInProgress());
    }

    public function test_a_student_no_longer_enrolled_cannot_submit(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->enrollment->delete();

        $this->submit($attempt)
            ->assertRedirect(route('courses.show', $this->course))
            ->assertSessionHas('error', 'Enroll in this course to open its lessons.');

        $this->assertTrue($attempt->fresh()->isInProgress());
    }

    public function test_guests_and_staff_cannot_submit(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $url = route('courses.quizzes.submit', [$this->course, $this->quiz, $attempt]);

        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs($this->instructor)->post($url)->assertForbidden();

        $this->assertTrue($attempt->fresh()->isInProgress());
    }

    // ---------- Helpers ----------

    /**
     * @param  array<int, string>  $choices  question position => 'right' | 'wrong'
     */
    private function answer(QuizAttempt $attempt, array $choices): void
    {
        foreach ($attempt->answers as $answer) {
            if (! isset($choices[$answer->position])) {
                continue;
            }

            $optionId = $choices[$answer->position] === 'right'
                ? $answer->correct_option_id
                : collect($answer->options)->firstWhere('id', '!=', $answer->correct_option_id)['id'];

            $this->actingAs($this->student)
                ->patchJson(route('courses.quizzes.answers', [$this->course, $this->quiz, $attempt]), [
                    'answer_id' => $answer->id,
                    'option_id' => $optionId,
                ])
                ->assertOk();
        }
    }

    private function resultsUrl(QuizAttempt $attempt): string
    {
        return route('courses.quizzes.results', [$this->course, $this->quiz, $attempt]);
    }

    private function submit(QuizAttempt $attempt)
    {
        return $this->actingAs($this->student)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz, $attempt]));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
