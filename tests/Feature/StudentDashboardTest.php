<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = $this->userWithRole('student');
        $this->instructor = $this->userWithRole('instructor');
    }

    public function test_a_student_without_courses_sees_real_zeros_and_the_empty_state(): void
    {
        $this->actingAs($this->student)->get(route('student.courses.index'))->assertOk();

        $response = $this->actingAs($this->student)->get('/student/dashboard')->assertOk();

        $this->assertSame([0, 0, 0, 0], $this->statValues($response));
        $response->assertSee("You haven't enrolled in any courses yet.", false)
            ->assertDontSee('View My Courses');
    }

    public function test_a_student_with_courses_in_progress(): void
    {
        [$course, $lessons] = $this->courseWithLessons('Laravel Basics', 4);
        $this->enroll($course);
        $this->student->completedLessons()->attach($lessons[0]->id);

        $response = $this->actingAs($this->student)->get('/student/dashboard')->assertOk();

        // Enrolled, In progress, Completed, Certificates
        $this->assertSame([1, 1, 0, 0], $this->statValues($response));
        $response->assertSee('Laravel Basics')
            ->assertSee('1 of 4 lessons')
            ->assertSee('25%')
            ->assertSee('href="' . route('courses.learn', $course) . '"', false)
            // The old placeholder button linked to "#".
            ->assertDontSee('<a href="#" class="btn btn-outline-primary btn-sm mt-auto">', false);
    }

    public function test_a_student_who_completed_a_course(): void
    {
        [$done, $doneLessons] = $this->courseWithLessons('Done Course', 2);
        [$busy, $busyLessons] = $this->courseWithLessons('Busy Course', 2);
        $this->enroll($done);
        $this->enroll($busy);
        $this->student->completedLessons()->attach([$doneLessons[0]->id, $doneLessons[1]->id, $busyLessons[0]->id]);

        $response = $this->actingAs($this->student)->get('/student/dashboard')->assertOk();

        $this->assertSame([2, 1, 1, 1], $this->statValues($response));
        $response->assertSee('Busy Course')            // in Continue Learning
            ->assertDontSee('Done Course');            // completed courses are not "in progress"
    }

    public function test_only_completed_courses_left_shows_a_well_done_message(): void
    {
        [$done, $lessons] = $this->courseWithLessons('Done Course', 1);
        $this->enroll($done);
        $this->student->completedLessons()->attach($lessons[0]->id);

        $this->actingAs($this->student)->get('/student/dashboard')
            ->assertSee('You have completed all your courses. Well done!');
    }

    public function test_the_numbers_match_my_courses(): void
    {
        [$a, $aLessons] = $this->courseWithLessons('A', 1);
        [$b] = $this->courseWithLessons('B', 3);
        [$c] = $this->courseWithLessons('C', 0); // no lessons: 0%, in progress
        $this->enroll($a);
        $this->enroll($b);
        $this->enroll($c);
        $this->student->completedLessons()->attach($aLessons[0]->id);

        [$enrolled, $inProgress, $completed] = $this->statValues(
            $this->actingAs($this->student)->get('/student/dashboard')
        );

        $myCourses = $this->actingAs($this->student)->get(route('student.courses.index'))->viewData('counts');

        $this->assertSame([$myCourses['all'], $myCourses['in-progress'], $myCourses['completed']], [$enrolled, $inProgress, $completed]);
        $this->assertSame([3, 2, 1], [$enrolled, $inProgress, $completed]);
    }

    public function test_a_student_only_sees_their_own_numbers(): void
    {
        $other = $this->userWithRole('student');
        [$course, $lessons] = $this->courseWithLessons('Someone Else', 1);
        Enrollment::create(['student_id' => $other->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        $other->completedLessons()->attach($lessons[0]->id);

        $response = $this->actingAs($this->student)->get('/student/dashboard');

        $this->assertSame([0, 0, 0, 0], $this->statValues($response));
        $response->assertDontSee('Someone Else');
    }

    public function test_draft_courses_are_not_counted(): void
    {
        [$course] = $this->courseWithLessons('Hidden', 1, 'draft');
        $this->enroll($course);

        $this->assertSame([0, 0, 0, 0], $this->statValues($this->actingAs($this->student)->get('/student/dashboard')));
    }

    /**
     * The 4 stat card numbers, in order: enrolled, in progress, completed, certificates.
     */
    private function statValues($response): array
    {
        preg_match_all('#<div class="stat-value">\s*(\d+)\s*</div>#', $response->getContent(), $matches);

        return array_map('intval', $matches[1]);
    }

    private function courseWithLessons(string $title, int $lessonCount, string $status = 'published'): array
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

        $lessons = [];
        for ($i = 1; $i <= $lessonCount; $i++) {
            $lessons[] = $section->lessons()->create(['title' => "Lesson $i", 'type' => 'article', 'order' => $i, 'content' => 'x']);
        }

        return [$course, $lessons];
    }

    private function enroll(Course $course): Enrollment
    {
        return Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
