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

class QuizStartPageTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructor;
    private Course $course;
    private Section $section;
    private Lesson $article;
    private Lesson $quiz;

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
        $this->article = $this->section->lessons()->create(['title' => 'Reading', 'type' => 'article', 'order' => 1, 'content' => 'Text']);
        $this->quiz = $this->section->lessons()->create([
            'title' => 'Chapter Quiz',
            'type' => 'quiz',
            'order' => 2,
            'content' => "Answer every question.\nGood luck!",
            'quiz_passing_score' => 80,
            'quiz_max_attempts' => 3,
        ]);

        // 2 questions, 5 points in total.
        $this->question('<p>Q1</p>', 2, 1);
        $this->question('<p>Q2</p>', 3, 2);

        $this->enroll($this->student);
    }

    // ---------- Start page ----------

    public function test_the_start_page_shows_the_quiz_details(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 15]);

        $this->actingAs($this->student)
            ->get($this->learnUrl())
            ->assertOk()
            ->assertSee('Chapter Quiz')
            ->assertSee('Answer every question.<br />', false)
            ->assertSeeInOrder(['Questions', '2', 'Total points', '5', 'Time limit', '15 minutes', 'Passing score', '80%'])
            ->assertSee('Attempt 1 of 3')
            ->assertSee('You have 15 minutes')
            ->assertSee('Start Quiz')
            ->assertSee(route('courses.quizzes.start', [$this->course, $this->quiz]), false)
            ->assertDontSee('Previous attempts')
            ->assertDontSee('Quiz questions are not available yet.');
    }

    public function test_without_settings_it_shows_no_time_limit_and_unlimited_attempts(): void
    {
        $this->quiz->update(['quiz_max_attempts' => null, 'content' => null]);

        $this->actingAs($this->student)
            ->get($this->learnUrl())
            ->assertOk()
            ->assertSee('No time limit')
            ->assertSee('Attempt 1 · Unlimited attempts')
            ->assertDontSee('You have ')
            ->assertDontSee('Instructions')
            ->assertSee('Start Quiz');
    }

    public function test_previous_attempts_are_listed_with_score_and_status(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 30));
        $failed = $this->submittedAttempt(score: 2);      // 2 / 5 = 40% -> failed
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(14, 5));
        $passed = $this->submittedAttempt(score: 5);      // 100% -> passed

        $this->actingAs($this->student)
            ->get($this->learnUrl())
            ->assertOk()
            ->assertSee('Previous attempts')
            ->assertSeeInOrder([
                '#2', 'Oct 21, 2026 2:05 PM', '5 / 5', '(100%)', 'Passed',
                '#1', 'Oct 20, 2026 9:30 AM', '2 / 5', '(40%)', 'Failed',
            ])
            ->assertSee('You passed this quiz.')
            ->assertSee('Attempt 3 of 3')
            ->assertSee('Start Quiz');

        $this->assertTrue($passed->passed);
        $this->assertFalse($failed->passed);
    }

    public function test_an_attempt_in_progress_shows_resume(): void
    {
        QuizAttempt::start($this->quiz, $this->student);

        $this->actingAs($this->student)
            ->get($this->learnUrl())
            ->assertOk()
            ->assertSee('Resume Quiz')
            ->assertSee('Attempt 1 is in progress.')
            ->assertSee('Attempt 1 of 3')
            ->assertSee('In progress')
            ->assertDontSee('Start Quiz');
    }

    public function test_a_used_up_quiz_cannot_be_started_again(): void
    {
        $this->submittedAttempt(score: 0);
        $this->submittedAttempt(score: 0);
        $this->submittedAttempt(score: 0);

        $this->actingAs($this->student)
            ->get($this->learnUrl())
            ->assertOk()
            ->assertSee('All 3 attempts used')
            ->assertSee('You have used all 3 attempts for this quiz')
            ->assertDontSee('Start Quiz')
            ->assertDontSee('Resume Quiz')
            ->assertDontSee(route('courses.quizzes.start', [$this->course, $this->quiz]), false);
    }

    public function test_an_attempt_whose_time_ran_out_is_submitted_instead_of_resumed(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 10]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);

        $this->travel(11)->minutes();

        $this->actingAs($this->student)
            ->get($this->learnUrl())
            ->assertOk()
            ->assertDontSee('Resume Quiz')
            ->assertSee("Time's up", false)
            ->assertSee('Failed')
            ->assertSee('Attempt 2 of 3')
            ->assertSee('Start Quiz');

        $attempt->refresh();
        $this->assertTrue($attempt->isSubmitted());
        $this->assertTrue($attempt->timed_out);
    }

    public function test_a_quiz_without_questions_cannot_be_started(): void
    {
        $this->quiz->questions()->delete();

        $this->actingAs($this->student)
            ->get($this->learnUrl())
            ->assertOk()
            ->assertSee('This quiz has no questions yet')
            ->assertDontSee('Start Quiz');

        $this->actingAs($this->student)
            ->post(route('courses.quizzes.start', [$this->course, $this->quiz]))
            ->assertRedirect($this->learnUrl())
            ->assertSessionHas('error', 'This quiz has no questions yet.');

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_a_student_who_is_not_enrolled_sees_the_details_but_cannot_start(): void
    {
        $this->quiz->update(['is_free_preview' => true]);
        $visitor = $this->userWithRole('student');

        $this->actingAs($visitor)
            ->get($this->learnUrl())
            ->assertOk()
            ->assertSee('Passing score')
            ->assertSee('3 attempts allowed')
            ->assertSee('Enroll in this course to take this quiz.')
            ->assertDontSee('Start Quiz');

        $this->actingAs($visitor)
            ->post(route('courses.quizzes.start', [$this->course, $this->quiz]))
            ->assertRedirect(route('courses.show', $this->course))
            ->assertSessionHas('error', 'Enroll in this course to open its lessons.');

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_a_quiz_that_is_not_a_free_preview_still_needs_enrolling(): void
    {
        $visitor = $this->userWithRole('student');

        $this->actingAs($visitor)
            ->get($this->learnUrl())
            ->assertRedirect(route('courses.show', $this->course));
    }

    public function test_the_staff_course_preview_shows_the_details_and_links_to_the_quiz_preview(): void
    {
        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.preview', [$this->course, $this->quiz]))
            ->assertOk()
            ->assertSee('Passing score')
            ->assertSee(route('instructor.courses.lessons.questions.preview', [$this->course, $this->quiz]), false)
            ->assertDontSee('Start Quiz');
    }

    // ---------- Start / Resume ----------

    public function test_start_quiz_creates_the_first_attempt(): void
    {
        $response = $this->actingAs($this->student)
            ->post(route('courses.quizzes.start', [$this->course, $this->quiz]));

        $attempt = QuizAttempt::sole();
        $response->assertRedirect(route('courses.quizzes.attempt', [$this->course, $this->quiz, $attempt]));
        $this->assertSame($this->student->id, $attempt->student_id);
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame(2, $attempt->answers()->count());
    }

    public function test_starting_again_resumes_the_attempt_in_progress(): void
    {
        $this->actingAs($this->student)->post(route('courses.quizzes.start', [$this->course, $this->quiz]));
        $attempt = QuizAttempt::sole();

        $this->actingAs($this->student)->post(route('courses.quizzes.start', [$this->course, $this->quiz]))
            ->assertRedirect(route('courses.quizzes.attempt', [$this->course, $this->quiz, $attempt]));

        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_the_backend_refuses_a_new_attempt_when_none_are_left(): void
    {
        $this->submittedAttempt(score: 0);
        $this->submittedAttempt(score: 0);
        $this->submittedAttempt(score: 0);

        $this->actingAs($this->student)
            ->post(route('courses.quizzes.start', [$this->course, $this->quiz]))
            ->assertRedirect($this->learnUrl())
            ->assertSessionHas('error', 'You have used all your attempts for this quiz.');

        $this->assertSame(3, QuizAttempt::count());
    }

    public function test_only_quiz_lessons_of_published_courses_can_be_started(): void
    {
        $this->actingAs($this->student)
            ->post(route('courses.quizzes.start', [$this->course, $this->article]))
            ->assertNotFound();

        $otherCourse = $this->course->replicate()->fill(['title' => 'Other']);
        $otherCourse->save();

        $this->actingAs($this->student)
            ->post(route('courses.quizzes.start', [$otherCourse, $this->quiz]))
            ->assertNotFound();

        $this->course->update(['status' => 'draft']);

        $this->actingAs($this->student)
            ->post(route('courses.quizzes.start', [$this->course, $this->quiz]))
            ->assertNotFound();

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_only_students_can_start_a_quiz(): void
    {
        $this->post(route('courses.quizzes.start', [$this->course, $this->quiz]))
            ->assertRedirect(route('login'));

        $this->actingAs($this->instructor)
            ->post(route('courses.quizzes.start', [$this->course, $this->quiz]))
            ->assertForbidden();

        $this->assertSame(0, QuizAttempt::count());
    }

    // ---------- Mark as Complete ----------

    public function test_a_quiz_lesson_has_no_mark_as_complete_button(): void
    {
        $this->actingAs($this->student)
            ->get($this->learnUrl())
            ->assertOk()
            ->assertDontSee('Mark as Complete')
            ->assertDontSee(route('courses.lessons.complete', [$this->course, $this->quiz]), false);

        // Other lessons keep it.
        $this->actingAs($this->student)
            ->get(route('courses.learn', [$this->course, $this->article]))
            ->assertOk()
            ->assertSee('Mark as Complete');
    }

    public function test_a_quiz_lesson_cannot_be_completed_by_hand(): void
    {
        $this->actingAs($this->student)
            ->post(route('courses.lessons.complete', [$this->course, $this->quiz]))
            ->assertRedirect($this->learnUrl())
            ->assertSessionHas('error', 'Pass the quiz to complete this lesson.');

        $this->actingAs($this->student)
            ->postJson(route('courses.lessons.complete', [$this->course, $this->quiz]))
            ->assertStatus(422)
            ->assertJson(['message' => 'Pass the quiz to complete this lesson.']);

        $this->assertSame(0, $this->student->completedLessons()->count());

        // Other lessons can still be completed by hand.
        $this->actingAs($this->student)
            ->post(route('courses.lessons.complete', [$this->course, $this->article]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->student->completedLessons()->whereKey($this->article->id)->exists());
    }

    // ---------- Helpers ----------

    private function learnUrl(): string
    {
        return route('courses.learn', [$this->course, $this->quiz]);
    }

    private function question(string $text, int $points, int $order): void
    {
        $question = $this->quiz->questions()->create(['question' => $text, 'points' => $points, 'order' => $order]);
        $question->options()->create(['text' => 'Right', 'is_correct' => true, 'order' => 1]);
        $question->options()->create(['text' => 'Wrong', 'order' => 2]);
    }

    /**
     * A submitted attempt with the given score (answering the first questions right).
     */
    private function submittedAttempt(int $score): QuizAttempt
    {
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        $earned = 0;

        foreach ($attempt->answers as $answer) {
            if ($earned + $answer->points <= $score) {
                $answer->update(['selected_option_id' => $answer->correct_option_id]);
                $earned += $answer->points;
            }
        }

        $attempt->grade();

        return $attempt;
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
