<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Mark as Complete" sent with fetch() (JSON), so the page updates without reloading.
 * The normal form POST (redirect) is covered by LearningProgressTest.
 */
class LessonCompletionAjaxTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructor;
    private Course $course;

    /** @var Lesson[] */
    private array $lessons = [];

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

        // Section "Intro": Welcome, Reading. Section "More": Final.
        $intro = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);
        $more = Section::create(['course_id' => $this->course->id, 'title' => 'More', 'order' => 2]);
        $this->lessons[] = $intro->lessons()->create(['title' => 'Welcome', 'type' => 'article', 'order' => 1, 'content' => 'x']);
        $this->lessons[] = $intro->lessons()->create(['title' => 'Reading', 'type' => 'article', 'order' => 2, 'content' => 'x']);
        $this->lessons[] = $more->lessons()->create(['title' => 'Final', 'type' => 'article', 'order' => 1, 'content' => 'x']);

        Enrollment::create(['student_id' => $this->student->id, 'course_id' => $this->course->id, 'enrolled_at' => now()]);
    }

    public function test_completing_a_lesson_returns_the_new_progress(): void
    {
        $this->complete(0)
            ->assertOk()
            ->assertExactJson([
                'message' => 'Lesson completed: Welcome',
                'lessonId' => $this->lessons[0]->id,
                'sectionId' => $this->lessons[0]->section_id,
                'sectionCompletedLessons' => 1,
                'completedLessons' => 1,
                'totalLessons' => 3,
                'progress' => 33,
                'courseCompleted' => false,
                'certificateUrl' => null,
                'nextUrl' => route('courses.learn', [$this->course, $this->lessons[1]]),
            ])
            // The page moves on to the next lesson by itself; the message is shown there, like before.
            ->assertSessionHas('success', 'Lesson completed: Welcome');

        $this->assertDatabaseHas('lesson_completions', ['student_id' => $this->student->id, 'lesson_id' => $this->lessons[0]->id]);
        $this->assertSame(33, $this->enrollment()->progress);
    }

    public function test_the_section_count_and_progress_add_up(): void
    {
        $this->complete(0);

        $this->complete(1)
            ->assertJson([
                'sectionCompletedLessons' => 2,   // both lessons of "Intro"
                'completedLessons' => 2,
                'progress' => 66,
                'nextUrl' => route('courses.learn', [$this->course, $this->lessons[2]]),
            ]);
    }

    public function test_completing_the_last_lesson_completes_the_course(): void
    {
        $this->complete(0);
        $this->complete(1);

        $this->complete(2)
            ->assertOk()
            ->assertJson([
                'message' => 'Congratulations! You completed Laravel Basics. Your certificate is ready.',
                'progress' => 100,
                'courseCompleted' => true,
                'certificateUrl' => route('courses.certificate', $this->course),
                'nextUrl' => null,
            ])
            // The page stays on this lesson and shows the message itself: nothing waits for the next page.
            ->assertSessionMissing('success');

        $this->assertNotNull($this->enrollment()->certificate_code);
    }

    public function test_the_last_lesson_without_completing_the_course_stays_on_the_page(): void
    {
        $this->complete(2)
            ->assertJson(['courseCompleted' => false, 'nextUrl' => null, 'progress' => 33])
            ->assertSessionMissing('success');
    }

    public function test_completing_twice_keeps_one_record(): void
    {
        $this->complete(0);
        $this->complete(0)->assertOk()->assertJson(['completedLessons' => 1, 'progress' => 33]);

        $this->assertSame(1, $this->student->completedLessons()->count());
    }

    public function test_errors_come_back_as_json(): void
    {
        // Not enrolled
        $outsider = $this->userWithRole('student');
        $this->actingAs($outsider)
            ->postJson($this->url(0))
            ->assertForbidden()
            ->assertExactJson(['message' => 'Enroll in this course to track your progress.']);

        // Guest
        auth()->logout();
        $this->postJson($this->url(0))->assertUnauthorized();

        // Instructor (route allows students only)
        $this->actingAs($this->instructor)->postJson($this->url(0))->assertForbidden();

        // A lesson from another course
        $other = Course::create(['title' => 'Other', 'short_description' => 's', 'description' => 'd', 'category_id' => $this->course->category_id, 'instructor_id' => $this->instructor->id, 'difficulty' => 'beginner', 'status' => 'published']);
        $this->actingAs($this->student)
            ->postJson(route('courses.lessons.complete', [$other, $this->lessons[0]]))
            ->assertNotFound();

        $this->assertDatabaseCount('lesson_completions', 0);
    }

    public function test_the_learning_page_has_the_elements_the_script_updates(): void
    {
        $html = $this->actingAs($this->student)
            ->get(route('courses.learn', [$this->course, $this->lessons[0]]))
            ->assertOk()
            ->getContent();

        foreach ([
            'data-course-progress-text', 'data-course-progress-bar', 'data-course-progress',
            'data-certificate-link', 'data-lesson-completed-badge', 'data-learn-messages',
            'data-complete-form', 'data-complete-done', 'data-complete-title', 'data-complete-text',
            'data-lesson-icon="' . $this->lessons[0]->id . '"', 'data-section-done="' . $this->lessons[0]->section_id . '"',
            "querySelector('[data-complete-form]')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "missing: $needle");
        }

        // Hidden until needed.
        $this->assertMatchesRegularExpression('#class="btn btn-primary btn-sm d-none"\s+data-certificate-link#', $html);
        $this->assertMatchesRegularExpression('#class="btn btn-success btn-lg d-none"\s+disabled\s+data-complete-done#', $html);
    }

    public function test_guests_watching_a_free_preview_get_no_script(): void
    {
        $this->lessons[0]->update(['is_free_preview' => true]);

        $this->get(route('courses.learn', [$this->course, $this->lessons[0]]))
            ->assertOk()
            ->assertDontSee('data-complete-form', false)
            ->assertDontSee("querySelector('[data-complete-form]')", false);
    }

    // ---------- Helpers ----------

    private function url(int $index): string
    {
        return route('courses.lessons.complete', [$this->course, $this->lessons[$index]]);
    }

    private function complete(int $index)
    {
        return $this->actingAs($this->student)->postJson($this->url($index));
    }

    private function enrollment(): Enrollment
    {
        return Enrollment::where('student_id', $this->student->id)->where('course_id', $this->course->id)->sole();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
