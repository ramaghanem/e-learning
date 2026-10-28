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

class QuizResultsTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructor;
    private Course $course;
    private Section $section;
    private Lesson $reading;
    private Lesson $quiz;
    private Lesson $final;
    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();

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

        // Reading -> Chapter Quiz (3 attempts, 70%) -> Final Words
        $this->section = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);
        $this->reading = $this->section->lessons()->create(['title' => 'Reading', 'type' => 'article', 'order' => 1, 'content' => 'Text']);
        $this->quiz = $this->section->lessons()->create([
            'title' => 'Chapter Quiz',
            'type' => 'quiz',
            'order' => 2,
            'quiz_max_attempts' => 3,
        ]);
        $this->final = $this->section->lessons()->create(['title' => 'Final Words', 'type' => 'article', 'order' => 3, 'content' => 'Bye']);

        // 3 questions: 1, 1 and 2 points.
        foreach ([['Capital of France?', 1, 'Paris', 'London'], ['2 + 2?', 1, 'Four', 'Five'], ['Pick blue', 2, 'Blue', 'Red']] as $order => [$text, $points, $right, $wrong]) {
            $question = $this->quiz->questions()->create(['question' => '<p>' . $text . '</p>', 'points' => $points, 'order' => $order + 1]);
            $question->options()->create(['text' => $right, 'is_correct' => true, 'order' => 1]);
            $question->options()->create(['text' => $wrong, 'order' => 2]);
        }

        $this->enrollment = Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'enrolled_at' => now(),
        ]);
    }

    // ---------- Score ----------

    public function test_a_passed_attempt_shows_the_score_and_the_next_lesson(): void
    {
        $attempt = $this->finishedAttempt([1 => 'right', 3 => 'right'], minutes: 12);   // 3 / 4 = 75%

        $this->results($attempt)
            ->assertOk()
            ->assertSee('PASSED')
            ->assertSee('You scored 3 out of 4')
            ->assertSee('75%')
            ->assertSee('Completed in 12 minutes')
            ->assertSee('Passing score was 70%')
            ->assertSee('Congratulations!')
            ->assertSee('Continue to Next Lesson')
            ->assertSee(route('courses.learn', [$this->course, $this->final]), false)
            ->assertDontSee('FAILED')
            ->assertDontSee('Retake Quiz')
            ->assertDontSee('View Certificate');
    }

    public function test_passing_the_last_lesson_of_the_course_offers_the_certificate(): void
    {
        $this->final->delete();   // the quiz is now the last lesson
        $this->actingAs($this->student)->post(route('courses.lessons.complete', [$this->course, $this->reading]));

        $attempt = $this->finishedAttempt([1 => 'right', 2 => 'right', 3 => 'right']);

        $this->results($attempt)
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('You have completed the whole course!')
            ->assertSee('View Certificate')
            ->assertSee(route('courses.certificate', $this->course), false)
            ->assertSee('Back to Course')
            ->assertDontSee('Continue to Next Lesson');
    }

    public function test_a_failed_attempt_encourages_and_offers_a_retake(): void
    {
        $attempt = $this->finishedAttempt([1 => 'right'], minutes: 0);   // 1 / 4 = 25%

        $this->results($attempt)
            ->assertOk()
            ->assertSee('FAILED')
            ->assertSee('You scored 1 out of 4')
            ->assertSee('25%')
            ->assertSee('Completed in less than a minute')
            ->assertSee('Passing score was 70%')
            ->assertSee("Don't give up!", false)
            ->assertSee('Review the material and try again.')
            ->assertSee('You have 2 attempts left.')
            ->assertSee('Retake Quiz')
            ->assertSee(route('courses.quizzes.start', [$this->course, $this->quiz]), false)
            ->assertSee('Review Course Material')
            ->assertSee(route('courses.learn', [$this->course, $this->reading]), false)
            ->assertDontSee('PASSED')
            ->assertDontSee('Continue to Next Lesson');
    }

    public function test_with_no_attempts_left_there_is_no_retake(): void
    {
        $this->finishedAttempt([]);
        $this->finishedAttempt([]);
        $last = $this->finishedAttempt([]);

        $this->results($last)
            ->assertOk()
            ->assertSee('You have no attempts left for this quiz.')
            ->assertDontSee('Retake Quiz')
            ->assertSee('Review Course Material');
    }

    public function test_with_unlimited_attempts_the_student_can_always_retake(): void
    {
        $this->quiz->update(['quiz_max_attempts' => null]);
        $attempt = $this->finishedAttempt([]);

        $this->results($attempt)
            ->assertOk()
            ->assertSee('You can retake this quiz as many times as you need.')
            ->assertSee('Retake Quiz');
    }

    public function test_retake_quiz_starts_the_next_attempt(): void
    {
        $this->finishedAttempt([]);

        $this->actingAs($this->student)
            ->post(route('courses.quizzes.start', [$this->course, $this->quiz]))
            ->assertRedirect();

        $this->assertSame(2, QuizAttempt::latest('id')->first()->attempt_number);
    }

    public function test_a_timed_out_attempt_says_so(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 5]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->travel(6)->minutes();
        $this->submit($attempt)->assertSessionHas('error', "Time's up! Your attempt was submitted.");

        $this->results($attempt)
            ->assertOk()
            ->assertSee("Time's up: submitted automatically", false)
            ->assertSee('Completed in 5 minutes');
    }

    // ---------- Review Answers ----------

    public function test_the_review_shows_each_question_with_both_answers_and_points(): void
    {
        $attempt = $this->finishedAttempt([1 => 'right', 2 => 'wrong']);   // question 3 not answered

        $this->results($attempt)
            ->assertOk()
            ->assertSee('Review Answers')
            ->assertSeeInOrder([
                'Correct:', 'Question 1', '1 / 1', '<p>Capital of France?</p>', 'Your answer', 'Paris', 'Correct answer', 'Paris',
                'Incorrect:', 'Question 2', '0 / 1', '<p>2 + 2?</p>', 'Your answer', 'Five', 'Correct answer', 'Four',
                'Incorrect:', 'Question 3', '0 / 2', '<p>Pick blue</p>', 'Your answer', 'Not answered', 'Correct answer', 'Blue',
            ], false)
            ->assertDontSee('Explanation');
    }

    public function test_without_review_only_the_overall_score_is_shown(): void
    {
        $this->quiz->update(['quiz_show_review' => false]);
        $attempt = $this->finishedAttempt([1 => 'right']);

        $this->results($attempt)
            ->assertOk()
            ->assertSee('You scored 1 out of 4')
            ->assertSee('Answer review is not available for this quiz.')
            ->assertDontSee('Review Answers')
            ->assertDontSee('Correct answer')
            ->assertDontSee('Capital of France?')
            ->assertDontSee('Paris');
    }

    public function test_the_review_shows_the_quiz_as_it_was_when_the_attempt_started(): void
    {
        $attempt = $this->finishedAttempt([1 => 'wrong']);

        // Later, the instructor rewrites question 1 and deletes question 2.
        $first = $this->quiz->questions()->first();
        $first->update(['question' => '<p>Rewritten</p>']);
        $first->options()->update(['text' => 'Edited option']);
        $this->quiz->questions()->skip(1)->first()->delete();

        $this->results($attempt)
            ->assertOk()
            ->assertSee('You scored 0 out of 4')
            ->assertSeeInOrder(['Capital of France?', 'London', 'Paris', '2 + 2?', 'Not answered', 'Four'])
            ->assertDontSee('Rewritten')
            ->assertDontSee('Edited option');
    }

    // ---------- Getting to the results ----------

    public function test_submitting_and_opening_a_submitted_attempt_lead_to_the_results(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);

        $this->submit($attempt)->assertRedirect($this->resultsUrl($attempt));

        $this->actingAs($this->student)
            ->get(route('courses.quizzes.attempt', [$this->course, $this->quiz, $attempt]))
            ->assertRedirect($this->resultsUrl($attempt));
    }

    public function test_an_attempt_in_progress_has_no_results_yet(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);

        $this->results($attempt)
            ->assertRedirect(route('courses.quizzes.attempt', [$this->course, $this->quiz, $attempt]));

        $this->assertTrue($attempt->fresh()->isInProgress());
    }

    public function test_an_attempt_whose_time_ran_out_is_graded_when_its_results_are_opened(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 5]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        $this->travel(1)->days();

        $this->results($attempt)
            ->assertOk()
            ->assertSee('FAILED')
            ->assertSee("Time's up: submitted automatically", false);

        $this->assertTrue($attempt->fresh()->timed_out);
    }

    public function test_the_start_page_links_each_submitted_attempt_to_its_results(): void
    {
        $submitted = $this->finishedAttempt([1 => 'right']);
        $open = QuizAttempt::start($this->quiz, $this->student);

        $this->actingAs($this->student)
            ->get(route('courses.learn', [$this->course, $this->quiz]))
            ->assertOk()
            ->assertSee('View Results')
            ->assertSee($this->resultsUrl($submitted), false)
            ->assertDontSee($this->resultsUrl($open), false);
    }

    // ---------- Access ----------

    public function test_another_student_cannot_see_the_results(): void
    {
        $attempt = $this->finishedAttempt([]);
        $other = $this->userWithRole('student');
        Enrollment::create(['student_id' => $other->id, 'course_id' => $this->course->id, 'enrolled_at' => now()]);

        $this->actingAs($other)->get($this->resultsUrl($attempt))->assertForbidden();
    }

    public function test_the_attempt_must_belong_to_the_quiz_in_the_url(): void
    {
        $attempt = $this->finishedAttempt([]);
        $otherQuiz = $this->section->lessons()->create(['title' => 'Quiz 2', 'type' => 'quiz', 'order' => 4]);

        $this->actingAs($this->student)
            ->get(route('courses.quizzes.results', [$this->course, $otherQuiz, $attempt]))
            ->assertNotFound();
    }

    public function test_a_student_no_longer_enrolled_is_sent_to_the_course_page(): void
    {
        $attempt = $this->finishedAttempt([]);
        $this->enrollment->delete();

        $this->results($attempt)
            ->assertRedirect(route('courses.show', $this->course))
            ->assertSessionHas('error', 'Enroll in this course to open its lessons.');
    }

    public function test_guests_and_staff_cannot_see_the_results(): void
    {
        // Made without logging in, so the first request really is a guest.
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $attempt->grade();

        $this->get($this->resultsUrl($attempt))->assertRedirect(route('login'));
        $this->actingAs($this->instructor)->get($this->resultsUrl($attempt))->assertForbidden();
    }

    // ---------- Helpers ----------

    /**
     * Starts an attempt, answers it, waits $minutes and submits it.
     *
     * @param  array<int, string>  $choices  question position => 'right' | 'wrong'
     */
    private function finishedAttempt(array $choices, int $minutes = 3): QuizAttempt
    {
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        foreach ($attempt->answers as $answer) {
            if (! isset($choices[$answer->position])) {
                continue;
            }

            $optionId = $choices[$answer->position] === 'right'
                ? $answer->correct_option_id
                : collect($answer->options)->firstWhere('id', '!=', $answer->correct_option_id)['id'];

            $answer->update(['selected_option_id' => $optionId, 'answered_at' => now()]);
        }

        $this->travel($minutes)->minutes();
        $this->submit($attempt);

        return $attempt->refresh();
    }

    private function submit(QuizAttempt $attempt)
    {
        return $this->actingAs($this->student)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz, $attempt]));
    }

    private function results(QuizAttempt $attempt)
    {
        return $this->actingAs($this->student)->get($this->resultsUrl($attempt));
    }

    private function resultsUrl(QuizAttempt $attempt): string
    {
        return route('courses.quizzes.results', [$this->course, $this->quiz, $attempt]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
