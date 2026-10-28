<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAutoSaveTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructor;
    private Course $course;
    private Section $section;
    private Lesson $quiz;
    private QuizQuestion $q1;
    private QuizAttempt $attempt;
    private QuizAttemptAnswer $first;    // Paris, London
    private QuizAttemptAnswer $second;   // One, Two, Three

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

        $this->q1 = $this->question($this->quiz, '<p>Capital of France?</p>', ['Paris', 'London'], 1);
        $this->question($this->quiz, '<p>Pick two</p>', ['One', 'Two', 'Three'], 2);

        $this->enroll($this->student);

        $this->attempt = QuizAttempt::start($this->quiz, $this->student);
        [$this->first, $this->second] = $this->attempt->answers->all();
    }

    public function test_choosing_an_option_saves_it(): void
    {
        $this->freezeSecond();
        $paris = $this->optionId($this->first, 0);

        $this->saveAs($this->student, $this->first->id, $paris)
            ->assertOk()
            ->assertExactJson([
                'message' => 'Answer saved.',
                'answerId' => $this->first->id,
                'optionId' => $paris,
                'answered' => 1,
                'total' => 2,
            ]);

        $this->first->refresh();
        $this->assertSame($paris, $this->first->selected_option_id);
        $this->assertTrue($this->first->answered_at->equalTo(now()));
        $this->assertNull($this->second->fresh()->selected_option_id);

        // Nothing is graded yet.
        $this->assertNull($this->first->is_correct);
        $this->assertTrue($this->attempt->fresh()->isInProgress());
    }

    public function test_choosing_another_option_replaces_the_saved_one(): void
    {
        $this->saveAs($this->student, $this->first->id, $this->optionId($this->first, 0))->assertOk();
        $this->saveAs($this->student, $this->first->id, $this->optionId($this->first, 1))
            ->assertOk()
            ->assertJson(['answered' => 1]);

        $this->assertSame($this->optionId($this->first, 1), $this->first->fresh()->selected_option_id);
    }

    public function test_the_saved_answer_is_shown_after_reloading_the_quiz(): void
    {
        $two = $this->optionId($this->second, 1);
        $this->saveAs($this->student, $this->second->id, $two)->assertOk();

        $html = $this->actingAs($this->student)
            ->get(route('courses.quizzes.attempt', [$this->course, $this->quiz, $this->attempt]))
            ->assertOk()
            ->assertSee('1 of 2 answered')
            ->assertSee('data-saved-option="' . $two . '"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/value="' . $two . '"\s+data-answer-input\s+checked/', $html);
    }

    public function test_the_quiz_screen_has_what_the_auto_save_needs(): void
    {
        $this->actingAs($this->student)
            ->get(route('courses.quizzes.attempt', [$this->course, $this->quiz, $this->attempt]))
            ->assertOk()
            ->assertSee('data-save-url="' . route('courses.quizzes.answers', [$this->course, $this->quiz, $this->attempt]) . '"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('data-answer-id="' . $this->first->id . '"', false)
            ->assertSee('data-save-status', false);
    }

    public function test_an_option_of_another_question_is_refused(): void
    {
        $this->saveAs($this->student, $this->first->id, $this->optionId($this->second, 0))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['option_id' => 'Choose one of the options of this question.']);

        $this->assertNull($this->first->fresh()->selected_option_id);
    }

    public function test_an_option_added_after_the_attempt_started_is_refused(): void
    {
        $added = $this->q1->options()->create(['text' => 'Berlin', 'order' => 3]);

        $this->saveAs($this->student, $this->first->id, $added->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('option_id');

        $this->assertNull($this->first->fresh()->selected_option_id);
    }

    public function test_an_option_deleted_after_the_attempt_started_can_still_be_chosen(): void
    {
        // The attempt uses its own copy of the question.
        $london = $this->q1->options()->where('text', 'London')->first();
        $london->delete();

        $this->saveAs($this->student, $this->first->id, $london->id)->assertOk();

        $this->assertSame($london->id, $this->first->fresh()->selected_option_id);
    }

    public function test_a_question_of_another_attempt_is_refused(): void
    {
        $other = $this->userWithRole('student');
        $this->enroll($other);
        $otherAnswer = QuizAttempt::start($this->quiz, $other)->answers->first();

        $this->saveAs($this->student, $otherAnswer->id, $this->optionId($otherAnswer, 0))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answer_id' => 'This question is not part of your attempt.']);

        $this->assertNull($otherAnswer->fresh()->selected_option_id);
    }

    public function test_missing_or_invalid_fields_are_refused(): void
    {
        $url = route('courses.quizzes.answers', [$this->course, $this->quiz, $this->attempt]);

        $this->actingAs($this->student)->patchJson($url, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answer_id', 'option_id']);

        $this->actingAs($this->student)->patchJson($url, ['answer_id' => 'abc', 'option_id' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answer_id', 'option_id']);
    }

    public function test_nothing_is_saved_after_the_attempt_is_submitted(): void
    {
        $this->attempt->grade();

        $this->saveAs($this->student, $this->first->id, $this->optionId($this->first, 0))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'This attempt has already been submitted.',
                'redirect' => route('courses.quizzes.results', [$this->course, $this->quiz, $this->attempt]),
            ]);

        $this->assertNull($this->first->fresh()->selected_option_id);
    }

    public function test_nothing_is_saved_after_the_time_runs_out(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 5]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        $answer = $attempt->answers->first();

        // After the time limit and the few grace seconds.
        $this->travel(5)->minutes();
        $this->travel(QuizAttempt::GRACE_SECONDS)->seconds();

        $this->saveAs($this->student, $answer->id, $this->optionId($answer, 0), $attempt)
            ->assertStatus(409)
            ->assertJson([
                'message' => "Time's up! Your attempt was submitted.",
                'redirect' => route('courses.quizzes.results', [$this->course, $this->quiz, $attempt]),
            ]);

        $this->assertNull($answer->fresh()->selected_option_id);

        $attempt->refresh();
        $this->assertTrue($attempt->isSubmitted());
        $this->assertTrue($attempt->timed_out);
    }

    public function test_answers_can_be_saved_until_the_time_runs_out(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 5]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        $answer = $attempt->answers->first();

        $this->travel(4)->minutes();
        $this->travel(59)->seconds();

        $this->saveAs($this->student, $answer->id, $this->optionId($answer, 0), $attempt)->assertOk();
    }

    public function test_an_answer_arriving_a_few_seconds_late_is_still_saved(): void
    {
        $this->quiz->update(['quiz_time_limit_minutes' => 5]);
        $attempt = QuizAttempt::start($this->quiz->refresh(), $this->student);
        $answer = $attempt->answers->first();

        // Chosen just before 0:00, arriving just after it (network delay).
        $this->travel(5)->minutes();
        $this->travel(QuizAttempt::GRACE_SECONDS - 1)->seconds();

        $this->saveAs($this->student, $answer->id, $this->optionId($answer, 0), $attempt)->assertOk();

        $this->assertNotNull($answer->fresh()->selected_option_id);
        $this->assertTrue($attempt->fresh()->isInProgress());
    }

    public function test_another_student_cannot_save_into_the_attempt(): void
    {
        $other = $this->userWithRole('student');
        $this->enroll($other);

        $this->saveAs($other, $this->first->id, $this->optionId($this->first, 0))->assertForbidden();

        $this->assertNull($this->first->fresh()->selected_option_id);
    }

    public function test_the_attempt_must_belong_to_the_quiz_in_the_url(): void
    {
        $otherQuiz = $this->section->lessons()->create(['title' => 'Quiz 2', 'type' => 'quiz', 'order' => 2]);

        $this->actingAs($this->student)
            ->patchJson(route('courses.quizzes.answers', [$this->course, $otherQuiz, $this->attempt]), [
                'answer_id' => $this->first->id,
                'option_id' => $this->optionId($this->first, 0),
            ])
            ->assertNotFound();
    }

    public function test_a_student_no_longer_enrolled_cannot_save(): void
    {
        Enrollment::query()->delete();

        $this->saveAs($this->student, $this->first->id, $this->optionId($this->first, 0))
            ->assertForbidden()
            ->assertJson(['message' => 'Enroll in this course to open its lessons.']);

        $this->assertNull($this->first->fresh()->selected_option_id);
    }

    public function test_guests_and_staff_cannot_save(): void
    {
        $url = route('courses.quizzes.answers', [$this->course, $this->quiz, $this->attempt]);
        $data = ['answer_id' => $this->first->id, 'option_id' => $this->optionId($this->first, 0)];

        $this->patchJson($url, $data)->assertUnauthorized();
        $this->actingAs($this->instructor)->patchJson($url, $data)->assertForbidden();

        $this->assertNull($this->first->fresh()->selected_option_id);
    }

    public function test_without_javascript_it_redirects_back_to_the_quiz(): void
    {
        $this->actingAs($this->student)
            ->patch(route('courses.quizzes.answers', [$this->course, $this->quiz, $this->attempt]), [
                'answer_id' => $this->first->id,
                'option_id' => $this->optionId($this->first, 0),
            ])
            ->assertRedirect(route('courses.quizzes.attempt', [$this->course, $this->quiz, $this->attempt]));

        $this->assertNotNull($this->first->fresh()->selected_option_id);
    }

    // ---------- Helpers ----------

    private function saveAs(User $user, int $answerId, int $optionId, ?QuizAttempt $attempt = null)
    {
        return $this->actingAs($user)->patchJson(
            route('courses.quizzes.answers', [$this->course, $this->quiz, $attempt ?? $this->attempt]),
            ['answer_id' => $answerId, 'option_id' => $optionId]
        );
    }

    private function optionId(QuizAttemptAnswer $answer, int $index): int
    {
        return $answer->options[$index]['id'];
    }

    private function question(Lesson $quiz, string $text, array $options, int $order): QuizQuestion
    {
        $question = $quiz->questions()->create(['question' => $text, 'points' => 1, 'order' => $order]);

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
