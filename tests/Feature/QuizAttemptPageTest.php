<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAttemptPageTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructor;
    private Course $course;
    private Section $section;
    private Lesson $quiz;
    private QuizQuestion $q1;
    private QuizQuestion $q2;

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

        $this->section = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);
        $this->quiz = $this->section->lessons()->create(['title' => 'Chapter Quiz', 'type' => 'quiz', 'order' => 1]);

        $this->q1 = $this->question($this->quiz, '<p>Capital of <strong>France</strong>?</p>', 2, ['Paris', 'London'], 1);
        $this->q2 = $this->question($this->quiz, '<p>Pick two</p>', 3, ['One', 'Two', 'Three'], 2);

        $this->enroll($this->student);
    }

    public function test_the_student_sees_the_quiz_screen(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);

        $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertOk()
            ->assertSee('Chapter Quiz')
            ->assertSee('Laravel Basics · Attempt 1')
            ->assertSee('0 of 2 answered')
            ->assertSee('role="progressbar"', false)
            ->assertSeeInOrder([
                'Question 1 of 2', '2 points', '<p>Capital of <strong>France</strong>?</p>', 'A.', 'Paris', 'B.', 'London',
                'Question 2 of 2', '3 points', '<p>Pick two</p>', 'A.', 'One', 'B.', 'Two', 'C.', 'Three',
            ], false)
            ->assertSee('name="answers[' . $attempt->answers[0]->id . ']"', false)
            ->assertSee('data-previous', false)
            ->assertSee('data-next', false)
            ->assertSee('data-jump="0"', false)
            ->assertSee('data-jump="1"', false)
            ->assertSee('Question 2, not answered')
            ->assertSee(route('courses.learn', [$this->course, $this->quiz]), false);
    }

    public function test_the_correct_answers_are_not_in_the_page(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);

        $html = $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/correct/i', $html);
        $this->assertStringNotContainsString('data-correct', $html);
    }

    public function test_the_screen_shows_the_copy_saved_when_the_attempt_started(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);

        // The instructor edits the quiz while the student is taking it.
        $this->q1->update(['question' => '<p>Changed question</p>']);
        $this->q1->options()->first()->update(['text' => 'Changed option']);
        $this->q2->delete();
        $this->question($this->quiz, '<p>Added later</p>', 5, ['x', 'y'], 3);

        $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertOk()
            ->assertSee('Capital of')
            ->assertSee('Paris')
            ->assertSee('Pick two')
            ->assertSee('Question 2 of 2')
            ->assertDontSee('Changed question')
            ->assertDontSee('Changed option')
            ->assertDontSee('Added later');
    }

    public function test_saved_answers_are_checked_and_the_first_unanswered_question_opens(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $first = $attempt->answers[0];
        $paris = $first->options[0]['id'];
        $first->update(['selected_option_id' => $paris, 'answered_at' => now()]);

        $html = $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertOk()
            ->assertSee('1 of 2 answered')
            ->assertSee('Question 1, answered')
            ->assertSee('Question 2, not answered')
            ->assertSee('let current = 1;', false)
            ->getContent();

        // Only the saved option is checked.
        $this->assertMatchesRegularExpression('/value="' . $paris . '"\s+data-answer-input\s+checked/', $html);
        $this->assertSame(1, preg_match_all('/data-answer-input\s+checked/', $html));
    }

    public function test_a_resumed_attempt_with_every_answer_opens_the_first_question(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);

        foreach ($attempt->answers as $answer) {
            $answer->update(['selected_option_id' => $answer->options[0]['id']]);
        }

        $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertOk()
            ->assertSee('2 of 2 answered')
            ->assertSee('let current = 0;', false);
    }

    public function test_a_submitted_attempt_cannot_be_opened_again(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $attempt->grade();

        // Its result is shown instead.
        $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertRedirect(route('courses.quizzes.results', [$this->course, $this->quiz, $attempt]));
    }

    public function test_an_attempt_whose_time_ran_out_is_submitted_when_opened(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 5]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->travel(6)->minutes();

        $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertRedirect(route('courses.quizzes.results', [$this->course, $this->quiz, $attempt]))
            ->assertSessionHas('error', "Time's up! Your attempt was submitted.");

        $attempt->refresh();
        $this->assertTrue($attempt->isSubmitted());
        $this->assertTrue($attempt->timed_out);
    }

    public function test_another_student_cannot_open_the_attempt(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $other = $this->userWithRole('student');
        $this->enroll($other);

        $this->actingAs($other)
            ->get($this->attemptUrl($attempt))
            ->assertForbidden();
    }

    public function test_the_attempt_must_belong_to_the_quiz_in_the_url(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $otherQuiz = $this->section->lessons()->create(['title' => 'Quiz 2', 'type' => 'quiz', 'order' => 2]);

        $this->actingAs($this->student)
            ->get(route('courses.quizzes.attempt', [$this->course, $otherQuiz, $attempt]))
            ->assertNotFound();
    }

    public function test_a_student_no_longer_enrolled_is_sent_to_the_course_page(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        Enrollment::query()->delete();

        $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertRedirect(route('courses.show', $this->course))
            ->assertSessionHas('error', 'Enroll in this course to open its lessons.');
    }

    public function test_an_unpublished_course_hides_the_attempt(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);
        $this->course->update(['status' => 'draft']);

        $this->actingAs($this->student)
            ->get($this->attemptUrl($attempt))
            ->assertNotFound();
    }

    public function test_guests_and_staff_cannot_open_the_quiz_screen(): void
    {
        $attempt = QuizAttempt::start($this->quiz, $this->student);

        $this->get($this->attemptUrl($attempt))->assertRedirect(route('login'));

        $this->actingAs($this->instructor)
            ->get($this->attemptUrl($attempt))
            ->assertForbidden();
    }

    // ---------- Helpers ----------

    private function attemptUrl(QuizAttempt $attempt): string
    {
        return route('courses.quizzes.attempt', [$this->course, $this->quiz, $attempt]);
    }

    private function question(Lesson $quiz, string $text, int $points, array $options, int $order): QuizQuestion
    {
        $question = $quiz->questions()->create(['question' => $text, 'points' => $points, 'order' => $order]);

        foreach ($options as $index => $optionText) {
            $question->options()->create(['text' => $optionText, 'is_correct' => $index === 0, 'order' => $index + 1]);
        }

        return $question;
    }

    private function enroll(User $student): void
    {
        Enrollment::create(['student_id' => $student->id, 'course_id' => $this->course->id, 'enrolled_at' => now()]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
