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
 * The enrollment status becomes "completed" (with completed_at) when every lesson is done.
 */
class EnrollmentStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructor;
    private Course $course;
    private Section $section;

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

        $this->section = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);
        $this->lessons[] = $this->section->lessons()->create(['title' => 'One', 'type' => 'article', 'order' => 1, 'content' => 'x']);
        $this->lessons[] = $this->section->lessons()->create(['title' => 'Two', 'type' => 'article', 'order' => 2, 'content' => 'x']);
    }

    public function test_a_new_enrollment_is_active(): void
    {
        $this->actingAs($this->student)->post(route('courses.enroll', $this->course));

        $this->assertSame('active', $this->enrollment()->status);
        $this->assertNull($this->enrollment()->completed_at);
    }

    public function test_it_stays_active_until_the_last_lesson(): void
    {
        $this->enroll();

        $this->markComplete(0);

        $enrollment = $this->enrollment();
        $this->assertSame('active', $enrollment->status);
        $this->assertSame(50, $enrollment->progress);
        $this->assertNull($enrollment->completed_at);
    }

    public function test_completing_every_lesson_marks_the_enrollment_completed(): void
    {
        $this->enroll();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));

        $this->markComplete(0);
        $this->markComplete(1);

        $enrollment = $this->enrollment();
        $this->assertSame('completed', $enrollment->status);
        $this->assertSame(100, $enrollment->progress);
        $this->assertTrue($enrollment->isCompleted());
        $this->assertSame('2026-10-20 10:00:00', $enrollment->completed_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($enrollment->certificate_code);
    }

    public function test_the_completion_date_is_kept_on_later_visits(): void
    {
        $this->enroll();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));
        $this->markComplete(0);
        $this->markComplete(1);

        // Days later the student opens the pages again (they all recalculate progress).
        $this->travelTo(now()->setDate(2026, 10, 25));
        $this->actingAs($this->student)->get(route('student.courses.index'))->assertOk();
        $this->actingAs($this->student)->get('/student/dashboard')->assertOk();
        $this->actingAs($this->student)->get(route('courses.learn', $this->course))->assertOk();
        $this->actingAs($this->student)->postJson(route('courses.lessons.complete', [$this->course, $this->lessons[0]]))->assertOk();

        $enrollment = $this->enrollment();
        $this->assertSame('completed', $enrollment->status);
        $this->assertSame('2026-10-20 10:00:00', $enrollment->completed_at->format('Y-m-d H:i:s'));
    }

    public function test_a_lesson_added_later_makes_it_active_again_until_completed(): void
    {
        $this->enroll();
        $this->markComplete(0);
        $this->markComplete(1);
        $code = $this->enrollment()->certificate_code;

        $new = $this->section->lessons()->create(['title' => 'Three', 'type' => 'article', 'order' => 3, 'content' => 'x']);
        $this->actingAs($this->student)->get(route('student.courses.index'));

        $enrollment = $this->enrollment();
        $this->assertSame('active', $enrollment->status);
        $this->assertSame(66, $enrollment->progress);
        $this->assertNull($enrollment->completed_at);
        $this->actingAs($this->student)->get(route('courses.certificate', $this->course))->assertForbidden();

        // Completing the new lesson completes the course again, with the same certificate number.
        $this->actingAs($this->student)->postJson(route('courses.lessons.complete', [$this->course, $new]));

        $enrollment = $this->enrollment();
        $this->assertSame('completed', $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);
        $this->assertSame($code, $enrollment->certificate_code);
    }

    public function test_the_instructor_analytics_also_update_the_status(): void
    {
        $this->enroll();
        // Completed directly in the table, so only the course-level recalculation can update the status.
        $this->student->completedLessons()->attach([$this->lessons[0]->id, $this->lessons[1]->id]);

        $this->actingAs($this->instructor)->get(route('instructor.courses.analytics', $this->course))->assertOk();

        $this->assertSame('completed', $this->enrollment()->status);
        $this->assertNotNull($this->enrollment()->completed_at);
    }

    public function test_the_completed_status_matches_progress_certificate_and_statistics(): void
    {
        $this->enroll();
        $this->markComplete(0);
        $this->markComplete(1);

        $completed = Enrollment::where('status', 'completed')->count();

        // Same count everywhere: My Courses tab, dashboard, statistics, instructor analytics.
        $this->assertSame(1, $completed);
        $this->assertSame($completed, $this->actingAs($this->student)->get(route('student.courses.index'))->viewData('counts')['completed']);
        $this->assertSame($completed, $this->actingAs($this->student)->get(route('student.statistics'))->viewData('summary')['completed']);
        $this->assertSame($completed, $this->actingAs($this->instructor)->get(route('instructor.courses.analytics', $this->course))->viewData('counts')['completed']);
        $this->actingAs($this->student)->get(route('courses.certificate', $this->course))->assertOk();
    }

    // ---------- Helpers ----------

    private function markComplete(int $index): void
    {
        $this->actingAs($this->student)
            ->post(route('courses.lessons.complete', [$this->course, $this->lessons[$index]]))
            ->assertRedirect();
    }

    private function enroll(): void
    {
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'enrolled_at' => now(),
            'progress' => 0,
            'status' => 'active',
        ]);
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
