<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAndUserStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;
    private User $student;
    private Course $laravel;
    private Course $php;
    private Course $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole('admin');
        $this->instructor = $this->userWithRole('instructor');
        $this->student = $this->userWithRole('student');

        // Instructor: 3 courses (one draft).
        $this->laravel = $this->course('Laravel Basics', 2);  // 2 lessons
        $this->php = $this->course('PHP Basics', 1);           // 1 lesson
        $this->draft = $this->course('Draft Course', 1, 'draft');

        // Student: in progress in Laravel (1 of 2), completed PHP (1 of 1), enrolled in the draft too.
        $this->enroll($this->student, $this->laravel);
        $this->enroll($this->student, $this->php);
        $this->enroll($this->student, $this->draft);
        $this->student->completedLessons()->attach([
            $this->lessonIds($this->laravel)[0],
            $this->lessonIds($this->php)[0],
        ]);

        // A second student in both published courses (counted once per instructor).
        $other = $this->userWithRole('student');
        $this->enroll($other, $this->laravel);
        $this->enroll($other, $this->php);
    }

    // ---------- My Profile ----------

    public function test_student_profile_shows_account_information_only(): void
    {
        // Course numbers for students are on the dashboard and on My Statistics, not on the profile.
        $response = $this->actingAs($this->student)->get(route('profile.show'))->assertOk();

        $this->assertSame([], $this->statValues($response));
        $response->assertDontSee('Enrolled courses')
            ->assertDontSee('Completed courses')
            ->assertSee($this->student->email)
            ->assertSee('Member since');
    }

    public function test_instructor_profile_shows_real_course_and_student_counts(): void
    {
        $response = $this->actingAs($this->instructor)->get(route('profile.show'))->assertOk();

        // Courses created (drafts included, like the instructor dashboard), distinct students.
        $this->assertSame(['3', '2'], $this->statValues($response));

        $dashboard = $this->actingAs($this->instructor)->get('/instructor/dashboard');
        $this->assertSame(array_slice($this->statValues($dashboard), 0, 2), $this->statValues($response));
    }

    public function test_admin_profile_shows_real_user_and_course_counts(): void
    {
        $response = $this->actingAs($this->admin)->get(route('profile.show'))->assertOk();

        // admin + instructor + 2 students = 4 users, 3 courses
        $this->assertSame(['4', '3'], $this->statValues($response));
    }

    public function test_profile_numbers_start_at_real_zeros(): void
    {
        $newInstructor = $this->userWithRole('instructor');

        $this->assertSame(['0', '0'], $this->statValues($this->actingAs($newInstructor)->get(route('profile.show'))));
    }

    // ---------- Admin: user details ----------

    public function test_admin_sees_an_instructors_real_numbers_and_courses(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.show', $this->instructor))
            ->assertOk();

        $this->assertSame(['3', '2'], $this->statValues($response));

        $html = $response->getContent();
        $this->assertSame(2, $this->enrollmentsFor($html, 'Laravel Basics'));
        $this->assertSame(2, $this->enrollmentsFor($html, 'PHP Basics'));
        $this->assertSame(1, $this->enrollmentsFor($html, 'Draft Course'));
    }

    public function test_admin_sees_a_students_real_courses_and_progress(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.show', $this->student))
            ->assertOk()
            ->assertSee('Laravel Basics')
            ->assertSee('PHP Basics')
            ->assertDontSee('Draft Course');

        $html = $response->getContent();
        $this->assertSame(50, $this->progressFor($html, 'Laravel Basics'));
        $this->assertSame(100, $this->progressFor($html, 'PHP Basics'));
    }

    public function test_admin_sees_the_empty_states_for_users_without_data(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $this->userWithRole('student')))
            ->assertSee('No enrolled courses yet');

        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $this->userWithRole('instructor')))
            ->assertSee('No courses created yet');
    }

    // ---------- Helpers ----------

    private function statValues($response): array
    {
        preg_match_all('#<div class="stat-value">\s*(.*?)\s*</div>#s', $response->getContent(), $matches);

        return $matches[1];
    }

    private function enrollmentsFor(string $html, string $title): ?int
    {
        preg_match('#' . preg_quote($title, '#') . '\s*</td>.*?<td class="text-end">\s*(\d+)\s*</td>#s', $html, $match);

        return isset($match[1]) ? (int) $match[1] : null;
    }

    private function progressFor(string $html, string $title): ?int
    {
        preg_match('#' . preg_quote($title, '#') . '\s*</td>.*?<span class="small fw-semibold">\s*(\d+)%#s', $html, $match);

        return isset($match[1]) ? (int) $match[1] : null;
    }

    private function lessonIds(Course $course): array
    {
        return $course->sections()->with('lessons')->get()->flatMap->lessons->pluck('id')->all();
    }

    private function course(string $title, int $lessons, string $status = 'published'): Course
    {
        $course = Course::create([
            'title' => $title,
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => Category::firstOrCreate(['name' => 'Web'])->id,
            'instructor_id' => $this->instructor->id,
            'difficulty' => 'beginner',
            'price' => 0,
            'status' => $status,
        ]);

        $section = Section::create(['course_id' => $course->id, 'title' => 'S', 'order' => 1]);

        for ($i = 1; $i <= $lessons; $i++) {
            $section->lessons()->create(['title' => "Lesson $i", 'type' => 'article', 'order' => $i, 'content' => 'x']);
        }

        return $course;
    }

    private function enroll(User $student, Course $course): Enrollment
    {
        return Enrollment::create(['student_id' => $student->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
