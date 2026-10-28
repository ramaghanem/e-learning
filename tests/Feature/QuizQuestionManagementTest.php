<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizQuestionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;
    private Course $course;
    private Section $section;
    private Lesson $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole('admin');
        $this->instructor = $this->userWithRole('instructor');

        $this->course = $this->courseFor($this->instructor, 'Course A');
        $this->section = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);
        $this->quiz = $this->section->lessons()->create(['title' => 'Quiz 1', 'type' => 'quiz', 'order' => 1]);
    }

    public function test_the_add_question_form_offers_only_mcq(): void
    {
        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.questions.create', [$this->course, $this->quiz]))
            ->assertOk()
            ->assertSee('Question type')
            ->assertSee('<option value="mcq"', false)
            ->assertSee('Add Option')
            ->assertSee('quill', false)
            ->assertDontSee('Explanation')
            ->assertDontSee('type="file"', false);
    }

    public function test_instructor_can_add_an_mcq_question(): void
    {
        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.lessons.questions.store', [$this->course, $this->quiz]), $this->validData())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]));

        $question = $this->quiz->questions()->with('options')->sole();

        $this->assertSame('mcq', $question->type);
        $this->assertSame('<p>What is <strong>2 + 2</strong>?</p>', $question->question);
        $this->assertSame(1, $question->points);
        $this->assertSame(1, $question->order);
        $this->assertSame(['3', '4', '5'], $question->options->pluck('text')->all());
        $this->assertSame([false, true, false], $question->options->pluck('is_correct')->all());

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]))
            ->assertOk()
            ->assertSee('What is 2 + 2?')
            ->assertSee('Multiple Choice (MCQ)')
            ->assertSee('1 point');
    }

    public function test_admin_can_add_a_question_and_new_questions_go_last(): void
    {
        $this->quiz->questions()->create(['question' => '<p>First</p>', 'order' => 1]);

        $this->actingAs($this->admin)
            ->post(route('admin.courses.lessons.questions.store', [$this->course, $this->quiz]), $this->validData(['points' => 5]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.courses.lessons.questions.index', [$this->course, $this->quiz]));

        $question = QuizQuestion::latest('id')->first();

        $this->assertSame(2, $question->order);
        $this->assertSame(5, $question->points);
    }

    public function test_the_question_html_is_cleaned(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.courses.lessons.questions.store', [$this->course, $this->quiz]),
            $this->validData([
                'question' => '<p onclick="x()">Pick<script>alert(1)</script> <img src="x.png"></p>'
                    . '<p><a href="javascript:alert(1)">bad</a></p>',
            ])
        )->assertSessionHasNoErrors();

        $html = $this->quiz->questions()->sole()->question;

        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('Pick', $html);
    }

    public function test_invalid_questions_are_rejected(): void
    {
        $cases = [
            'question' => ['question' => '<p><br></p>'],
            'type' => ['type' => 'true_false'],
            'points' => ['points' => 0],
            'options' => ['options' => [['text' => 'Only one']], 'correct_option' => '0'],
            'options.0.text' => ['options' => [['text' => ''], ['text' => 'B']], 'correct_option' => '1'],
            'correct_option' => ['correct_option' => '9'],
        ];

        foreach ($cases as $field => $overrides) {
            $this->actingAs($this->admin)
                ->post(route('admin.courses.lessons.questions.store', [$this->course, $this->quiz]), $this->validData($overrides))
                ->assertSessionHasErrors($field);
        }

        // No correct answer chosen.
        $data = $this->validData();
        unset($data['correct_option']);

        $this->actingAs($this->admin)
            ->post(route('admin.courses.lessons.questions.store', [$this->course, $this->quiz]), $data)
            ->assertSessionHasErrors('correct_option');

        $this->assertSame(0, QuizQuestion::count());
    }

    public function test_a_question_has_two_to_six_options(): void
    {
        $seven = array_map(fn ($n) => ['text' => 'Option ' . $n], range(1, 7));

        $this->actingAs($this->admin)
            ->post(route('admin.courses.lessons.questions.store', [$this->course, $this->quiz]), $this->validData([
                'options' => $seven,
                'correct_option' => '0',
            ]))
            ->assertSessionHasErrors('options');

        $six = array_slice($seven, 0, 6);

        $this->actingAs($this->admin)
            ->post(route('admin.courses.lessons.questions.store', [$this->course, $this->quiz]), $this->validData([
                'options' => $six,
                'correct_option' => '5',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(6, $this->quiz->questions()->sole()->options()->count());
    }

    public function test_the_edit_form_is_filled_with_the_current_question(): void
    {
        $question = $this->createQuestion();

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.questions.edit', [$this->course, $this->quiz, $question]))
            ->assertOk()
            ->assertSee('Edit Question')
            ->assertSee('value="Paris"', false)
            ->assertSee('value="London"', false)
            ->assertSee('value="3"', false)
            ->assertSee('Capital of France?');
    }

    public function test_updating_keeps_edits_adds_and_removes_options(): void
    {
        $question = $this->createQuestion();
        [$paris, $london] = $question->options->all();

        $this->actingAs($this->instructor)
            ->patch(route('instructor.courses.lessons.questions.update', [$this->course, $this->quiz, $question]), [
                'type' => 'mcq',
                'question' => '<p>Capital of <em>France</em>?</p>',
                'points' => 2,
                'options' => [
                    'a' => ['id' => $paris->id, 'text' => 'Paris, France'],
                    'n1' => ['text' => 'Berlin'],
                ],
                'correct_option' => 'a',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]));

        $question->refresh()->load('options');

        $this->assertSame('<p>Capital of <em>France</em>?</p>', $question->question);
        $this->assertSame(2, $question->points);
        $this->assertSame(['Paris, France', 'Berlin'], $question->options->pluck('text')->all());
        $this->assertSame($paris->id, $question->options[0]->id);
        $this->assertSame([true, false], $question->options->pluck('is_correct')->all());
        $this->assertDatabaseMissing('quiz_question_options', ['id' => $london->id]);
    }

    public function test_an_option_of_another_question_cannot_be_reused(): void
    {
        $question = $this->createQuestion();
        $other = $this->createQuestion();

        $this->actingAs($this->admin)
            ->patch(route('admin.courses.lessons.questions.update', [$this->course, $this->quiz, $question]), [
                'type' => 'mcq',
                'question' => '<p>Q</p>',
                'points' => 1,
                'options' => [
                    0 => ['id' => $other->options[0]->id, 'text' => 'Stolen'],
                    1 => ['text' => 'B'],
                ],
                'correct_option' => '0',
            ])
            ->assertSessionHasErrors('options.0.id');

        $this->assertSame('Paris', $other->options[0]->fresh()->text);
    }

    public function test_another_instructor_and_students_cannot_manage_questions(): void
    {
        $otherInstructor = $this->userWithRole('instructor');
        $student = $this->userWithRole('student');
        $question = $this->createQuestion();

        foreach ([$otherInstructor, $student] as $user) {
            $this->actingAs($user)
                ->get(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('instructor.courses.lessons.questions.store', [$this->course, $this->quiz]), $this->validData())
                ->assertForbidden();

            $this->actingAs($user)
                ->patch(route('instructor.courses.lessons.questions.update', [$this->course, $this->quiz, $question]), $this->validData())
                ->assertForbidden();
        }

        $this->assertSame(1, QuizQuestion::count());
    }

    public function test_questions_only_belong_to_quiz_lessons_of_the_course(): void
    {
        $article = $this->section->lessons()->create(['title' => 'Read', 'type' => 'article', 'content' => 'Text', 'order' => 2]);

        $otherCourse = $this->courseFor($this->instructor, 'Course B');
        $otherSection = Section::create(['course_id' => $otherCourse->id, 'title' => 'S', 'order' => 1]);
        $otherQuiz = $otherSection->lessons()->create(['title' => 'Quiz B', 'type' => 'quiz', 'order' => 1]);
        $otherQuestion = $otherQuiz->questions()->create(['question' => '<p>B</p>', 'order' => 1]);

        $this->actingAs($this->admin)
            ->get(route('admin.courses.lessons.questions.create', [$this->course, $article]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('admin.courses.lessons.questions.create', [$this->course, $otherQuiz]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('admin.courses.lessons.questions.edit', [$this->course, $this->quiz, $otherQuestion]))
            ->assertNotFound();
    }

    public function test_the_course_content_page_links_to_the_questions_of_quiz_lessons_only(): void
    {
        $article = $this->section->lessons()->create(['title' => 'Read', 'type' => 'article', 'content' => 'Text', 'order' => 2]);

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.content', $this->course))
            ->assertOk()
            ->assertSee(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]), false)
            ->assertDontSee(route('instructor.courses.lessons.questions.index', [$this->course, $article]), false);
    }

    public function test_the_questions_list_shows_numbers_total_points_and_actions(): void
    {
        $first = $this->createQuestion();               // 3 points
        $second = $this->createQuestion();
        $second->update(['question' => '<p>Second one</p>', 'points' => 2]);

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]))
            ->assertOk()
            ->assertSee('2 questions')
            ->assertSee('Total: 5 points')
            ->assertSeeInOrder(['1.', 'Capital of France?', '2.', 'Second one'])
            ->assertSee(route('instructor.courses.lessons.questions.edit', [$this->course, $this->quiz, $first]), false)
            ->assertSee(route('instructor.courses.lessons.questions.destroy', [$this->course, $this->quiz, $second]), false)
            ->assertSee('deleteQuestionModal-' . $first->id)
            ->assertSee(route('instructor.courses.lessons.questions.reorder', [$this->course, $this->quiz]), false)
            ->assertSee('sortablejs', false);
    }

    public function test_a_question_can_be_deleted_with_its_options(): void
    {
        $question = $this->createQuestion();
        $kept = $this->createQuestion();

        $this->actingAs($this->instructor)
            ->delete(route('instructor.courses.lessons.questions.destroy', [$this->course, $this->quiz, $question]))
            ->assertRedirect(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]))
            ->assertSessionHas('success', 'Question deleted successfully.');

        $this->assertDatabaseMissing('quiz_questions', ['id' => $question->id]);
        $this->assertDatabaseMissing('quiz_question_options', ['quiz_question_id' => $question->id]);
        $this->assertDatabaseHas('quiz_questions', ['id' => $kept->id]);

        // The total follows: only the kept question (3 points) is left.
        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]))
            ->assertSee('1 question')
            ->assertSee('Total: 3 points');
    }

    public function test_only_allowed_users_can_delete_a_question_of_this_quiz(): void
    {
        $question = $this->createQuestion();

        foreach ([$this->userWithRole('instructor'), $this->userWithRole('student')] as $user) {
            $this->actingAs($user)
                ->delete(route('instructor.courses.lessons.questions.destroy', [$this->course, $this->quiz, $question]))
                ->assertForbidden();
        }

        $otherQuiz = $this->section->lessons()->create(['title' => 'Quiz 2', 'type' => 'quiz', 'order' => 2]);

        $this->actingAs($this->admin)
            ->delete(route('admin.courses.lessons.questions.destroy', [$this->course, $otherQuiz, $question]))
            ->assertNotFound();

        $this->assertDatabaseHas('quiz_questions', ['id' => $question->id]);
    }

    public function test_dragging_saves_the_new_order(): void
    {
        $a = $this->createQuestion();
        $b = $this->createQuestion();
        $c = $this->createQuestion();

        $this->actingAs($this->instructor)
            ->patchJson(route('instructor.courses.lessons.questions.reorder', [$this->course, $this->quiz]), [
                'questions' => [$c->id, $a->id, $b->id],
            ])
            ->assertOk()
            ->assertJson(['message' => 'Question order updated.']);

        $this->assertSame([$c->id, $a->id, $b->id], $this->quiz->questions()->pluck('id')->all());
        $this->assertSame([1, 2, 3], $this->quiz->questions()->pluck('order')->all());

        // Without JavaScript (a normal form post) it redirects back to the list.
        $this->actingAs($this->admin)
            ->patch(route('admin.courses.lessons.questions.reorder', [$this->course, $this->quiz]), [
                'questions' => [$b->id, $c->id, $a->id],
            ])
            ->assertRedirect(route('admin.courses.lessons.questions.index', [$this->course, $this->quiz]));

        $this->assertSame([$b->id, $c->id, $a->id], $this->quiz->questions()->pluck('id')->all());
    }

    public function test_the_new_order_must_list_exactly_the_questions_of_this_quiz(): void
    {
        $a = $this->createQuestion();
        $b = $this->createQuestion();

        $otherQuiz = $this->section->lessons()->create(['title' => 'Quiz 2', 'type' => 'quiz', 'order' => 2]);
        $foreign = $otherQuiz->questions()->create(['question' => '<p>Other</p>', 'order' => 1]);

        $invalid = [
            'missing a question' => [$b->id],
            'a question of another quiz' => [$b->id, $a->id, $foreign->id],
            'a question twice' => [$b->id, $a->id, $a->id],
            'not a list' => 'abc',
        ];

        foreach ($invalid as $questions) {
            $this->actingAs($this->instructor)
                ->patchJson(route('instructor.courses.lessons.questions.reorder', [$this->course, $this->quiz]), [
                    'questions' => $questions,
                ])
                ->assertStatus(422);
        }

        $this->assertSame([$a->id, $b->id], $this->quiz->questions()->pluck('id')->all());
        $this->assertSame(1, $foreign->fresh()->order);
    }

    public function test_only_allowed_users_can_reorder_questions(): void
    {
        $a = $this->createQuestion();
        $b = $this->createQuestion();

        foreach ([$this->userWithRole('instructor'), $this->userWithRole('student')] as $user) {
            $this->actingAs($user)
                ->patchJson(route('instructor.courses.lessons.questions.reorder', [$this->course, $this->quiz]), [
                    'questions' => [$b->id, $a->id],
                ])
                ->assertForbidden();
        }

        $this->assertSame([$a->id, $b->id], $this->quiz->questions()->pluck('id')->all());
    }

    public function test_the_questions_page_links_to_the_preview_only_when_there_are_questions(): void
    {
        $previewUrl = route('instructor.courses.lessons.questions.preview', [$this->course, $this->quiz]);

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]))
            ->assertDontSee($previewUrl, false);

        $this->createQuestion();

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]))
            ->assertSee($previewUrl, false)
            ->assertSee('Preview Quiz');
    }

    public function test_the_preview_shows_every_question_and_option_in_order(): void
    {
        $first = $this->createQuestion();                // Paris (correct), London; 3 points
        $second = $this->quiz->questions()->create(['question' => '<p>Pick <strong>two</strong></p>', 'points' => 2, 'order' => 0]);
        $second->options()->create(['text' => 'One', 'order' => 1]);
        $second->options()->create(['text' => 'Two', 'is_correct' => true, 'order' => 2]);
        $second->options()->create(['text' => 'Three', 'order' => 3]);

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.questions.preview', [$this->course, $this->quiz]))
            ->assertOk()
            ->assertSee('Preview Mode')
            ->assertSee('Your answers are not saved.')
            ->assertSee('2 questions')
            ->assertSee('5 points')
            ->assertSeeInOrder([
                'Question 1 of 2', '<p>Pick <strong>two</strong></p>', 'A.', 'One', 'B.', 'Two', 'C.', 'Three',
                'Question 2 of 2', '<p>Capital of France?</p>', 'A.', 'Paris', 'B.', 'London',
            ], false)
            ->assertSee('name="question-' . $first->id . '"', false)
            ->assertSee('name="question-' . $second->id . '"', false)
            ->assertSee('Check Answers')
            ->assertSee('Exit Preview')
            ->assertSee(route('instructor.courses.lessons.questions.index', [$this->course, $this->quiz]), false);
    }

    public function test_the_preview_form_is_never_sent_to_the_server(): void
    {
        $this->createQuestion();

        $html = $this->actingAs($this->admin)
            ->get(route('admin.courses.lessons.questions.preview', [$this->course, $this->quiz]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('~<form id="quiz-preview" novalidate>~', $html);
        $this->assertStringContainsString('event.preventDefault()', $html);
        $this->assertStringNotContainsString('fetch(', $html);
    }

    public function test_previewing_does_not_change_anything(): void
    {
        $question = $this->createQuestion();
        $tablesBefore = $this->rowCounts();
        $updatedAt = $question->updated_at;

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.lessons.questions.preview', [$this->course, $this->quiz]))
            ->assertOk();

        $this->assertSame($tablesBefore, $this->rowCounts());
        $this->assertEquals($updatedAt, $question->fresh()->updated_at);
    }

    public function test_an_empty_quiz_preview_offers_a_way_back(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.courses.lessons.questions.preview', [$this->course, $this->quiz]))
            ->assertOk()
            ->assertSee('This quiz has no questions yet')
            ->assertSee(route('admin.courses.lessons.questions.index', [$this->course, $this->quiz]), false)
            ->assertDontSee('Check Answers');
    }

    public function test_only_allowed_users_can_preview_a_quiz_of_the_course(): void
    {
        $this->createQuestion();

        foreach ([$this->userWithRole('instructor'), $this->userWithRole('student')] as $user) {
            $this->actingAs($user)
                ->get(route('instructor.courses.lessons.questions.preview', [$this->course, $this->quiz]))
                ->assertForbidden();
        }

        $article = $this->section->lessons()->create(['title' => 'Read', 'type' => 'article', 'content' => 'Text', 'order' => 2]);

        $this->actingAs($this->admin)
            ->get(route('admin.courses.lessons.questions.preview', [$this->course, $article]))
            ->assertNotFound();
    }

    private function rowCounts(): array
    {
        return collect(['lessons', 'quiz_questions', 'quiz_question_options', 'enrollments', 'lesson_completions'])
            ->mapWithKeys(fn ($table) => [$table => \Illuminate\Support\Facades\DB::table($table)->count()])
            ->all();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'type' => 'mcq',
            'question' => '<p>What is <strong>2 + 2</strong>?</p>',
            'points' => 1,
            'options' => [
                0 => ['text' => '3'],
                1 => ['text' => '4'],
                2 => ['text' => '5'],
            ],
            'correct_option' => '1',
        ], $overrides);
    }

    private function createQuestion(): QuizQuestion
    {
        $question = $this->quiz->questions()->create([
            'question' => '<p>Capital of France?</p>',
            'points' => 3,
            'order' => ((int) $this->quiz->questions()->max('order')) + 1,
        ]);

        $question->options()->create(['text' => 'Paris', 'is_correct' => true, 'order' => 1]);
        $question->options()->create(['text' => 'London', 'order' => 2]);

        return $question->load('options');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function courseFor(User $instructor, string $title): Course
    {
        $category = Category::create(['name' => 'Category for ' . $title]);

        return Course::create([
            'title' => $title,
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'difficulty' => 'beginner',
        ]);
    }
}
