<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole('admin');
        $this->instructor = $this->userWithRole('instructor');
    }

    // ---------- Instructor dashboard ----------

    public function test_an_instructor_without_courses_sees_zeros(): void
    {
        $response = $this->actingAs($this->instructor)->get('/instructor/dashboard')->assertOk();

        $this->assertSame(['0', '0', '—'], $this->statValues($response));
    }

    public function test_the_instructor_sees_real_enrollment_numbers(): void
    {
        $laravel = $this->course('Laravel Basics', $this->instructor);
        $php = $this->course('PHP Basics', $this->instructor);
        $empty = $this->course('No Students Yet', $this->instructor);

        [$sara, $omar, $lina] = [$this->userWithRole('student'), $this->userWithRole('student'), $this->userWithRole('student')];

        $this->enroll($sara, $laravel);
        $this->enroll($omar, $laravel);
        $this->enroll($sara, $php);   // Sara is in two courses: counted once as a student
        $this->enroll($lina, $php);

        $response = $this->actingAs($this->instructor)->get('/instructor/dashboard')->assertOk();

        // Courses created, students (distinct), average rating
        $this->assertSame(['3', '3', '—'], $this->statValues($response));
        $response->assertSee('No reviews yet');

        // Enrollments per course in the table.
        $this->assertSame(
            ['Laravel Basics' => 2, 'PHP Basics' => 2, 'No Students Yet' => 0],
            $this->enrollmentsPerCourse($response->getContent(), ['Laravel Basics', 'PHP Basics', 'No Students Yet'])
        );
    }

    public function test_an_instructor_only_counts_their_own_courses(): void
    {
        $mine = $this->course('Mine', $this->instructor);
        $other = $this->course('Not Mine', $this->userWithRole('instructor'));

        $this->enroll($this->userWithRole('student'), $mine);
        $this->enroll($this->userWithRole('student'), $other);
        $this->enroll($this->userWithRole('student'), $other);

        $response = $this->actingAs($this->instructor)->get('/instructor/dashboard');

        $this->assertSame(['1', '1', '—'], $this->statValues($response));
        $response->assertDontSee('Not Mine');
    }

    // ---------- Admin dashboard ----------

    public function test_the_admin_sees_the_total_and_the_latest_enrollments(): void
    {
        $course = $this->course('Laravel Basics', $this->instructor);
        $sara = $this->userWithRole('student');
        $omar = $this->userWithRole('student');
        $sara->forceFill(['name' => 'Sara Student'])->save();
        $omar->forceFill(['name' => 'Omar Student'])->save();

        $this->enroll($sara, $course, now()->subDays(2));
        $this->enroll($omar, $course, now());

        $response = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();

        // Users (admin + instructor + 2 students), courses, enrollments
        $this->assertSame(['4', '1', '2'], $this->statValues($response));
        $response->assertDontSee('No enrollments yet')
            ->assertSeeInOrder(['Recent Enrollments', 'Omar Student', 'Laravel Basics', now()->format('M j, Y'), 'Sara Student', now()->subDays(2)->format('M j, Y')]);
    }

    public function test_the_admin_sees_only_the_last_10_enrollments(): void
    {
        $course = $this->course('Laravel Basics', $this->instructor);

        for ($i = 1; $i <= 12; $i++) {
            $this->enroll($this->userWithRole('student'), $course, now()->subMinutes(20 - $i));
        }

        $response = $this->actingAs($this->admin)->get('/admin/dashboard');

        $this->assertSame('12', $this->statValues($response)[2]);
        $this->assertCount(10, $response->viewData('recentEnrollments'));
    }

    public function test_the_admin_empty_state_when_there_are_no_enrollments(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();

        $this->assertSame('0', $this->statValues($response)[2]);
        $response->assertSee('No enrollments yet');
    }

    // ---------- Helpers ----------

    /**
     * The numbers in the stat cards, in order.
     */
    private function statValues($response): array
    {
        preg_match_all('#<div class="stat-value">\s*(.*?)\s*</div>#s', $response->getContent(), $matches);

        return $matches[1];
    }

    /**
     * The "Enrollments" column of the instructor's course table, per course title.
     */
    private function enrollmentsPerCourse(string $html, array $titles): array
    {
        $result = [];

        foreach ($titles as $title) {
            preg_match('#' . preg_quote($title, '#') . '\s*</td>.*?<td class="text-end">\s*(\d+)\s*</td>#s', $html, $match);
            $result[$title] = isset($match[1]) ? (int) $match[1] : null;
        }

        return $result;
    }

    private function course(string $title, User $instructor): Course
    {
        return Course::create([
            'title' => $title,
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => Category::firstOrCreate(['name' => 'Web'])->id,
            'instructor_id' => $instructor->id,
            'difficulty' => 'beginner',
            'price' => 0,
            'status' => 'published',
        ]);
    }

    private function enroll(User $student, Course $course, $at = null): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'enrolled_at' => $at ?? now(),
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
