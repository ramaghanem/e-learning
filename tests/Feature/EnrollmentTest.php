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

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructor;
    private Course $course;
    private Lesson $freeLesson;
    private Lesson $lockedLesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = $this->userWithRole('student');
        $this->instructor = $this->userWithRole('instructor');
        $this->course = $this->publishedCourse('Laravel Basics', 0);

        $section = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);

        $this->freeLesson = $section->lessons()->create([
            'title' => 'Welcome video', 'type' => 'video', 'order' => 1,
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'duration_minutes' => 30, 'is_free_preview' => true,
        ]);

        $this->lockedLesson = $section->lessons()->create([
            'title' => 'Locked article', 'type' => 'article', 'order' => 2,
            'content' => 'Members only', 'is_free_preview' => false,
        ]);
    }

    // ---------- Enroll Now ----------

    public function test_a_guest_is_sent_to_login_and_comes_back_to_the_course(): void
    {
        $this->post(route('courses.enroll', $this->course))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Please login to enroll')
            ->assertSessionHas('url.intended', route('courses.show', $this->course));

        $this->post('/login', ['email' => $this->student->email, 'password' => 'password'])
            ->assertRedirect(route('courses.show', $this->course));

        $this->assertSame(0, Enrollment::count());
    }

    public function test_a_student_is_enrolled_with_the_default_values(): void
    {
        $this->actingAs($this->student)
            ->post(route('courses.enroll', $this->course))
            ->assertRedirect(route('courses.learn', $this->course))
            ->assertSessionHas('success', 'Successfully enrolled in Laravel Basics!');

        $enrollment = Enrollment::sole();
        $this->assertSame($this->student->id, $enrollment->student_id);
        $this->assertSame($this->course->id, $enrollment->course_id);
        $this->assertSame(0, $enrollment->progress);
        $this->assertSame('active', $enrollment->status);
        $this->assertNotNull($enrollment->enrolled_at);
    }

    public function test_a_paid_course_is_enrolled_right_away_too(): void
    {
        $paid = $this->publishedCourse('Paid Course', 49.99);

        $this->actingAs($this->student)
            ->post(route('courses.enroll', $paid))
            ->assertRedirect(route('courses.learn', $paid))
            ->assertSessionHas('success', 'Successfully enrolled in Paid Course!');

        $this->assertTrue(Enrollment::where('course_id', $paid->id)->exists());
    }

    public function test_enrolling_twice_keeps_one_enrollment(): void
    {
        $this->actingAs($this->student)->post(route('courses.enroll', $this->course));

        $this->actingAs($this->student)
            ->post(route('courses.enroll', $this->course))
            ->assertRedirect(route('courses.learn', $this->course))
            ->assertSessionHas('success', 'You are already enrolled in this course.');

        $this->assertSame(1, Enrollment::count());
    }

    public function test_only_students_can_enroll(): void
    {
        foreach ([$this->instructor, $this->userWithRole('admin')] as $user) {
            $this->actingAs($user)
                ->from(route('courses.show', $this->course))
                ->post(route('courses.enroll', $this->course))
                ->assertRedirect(route('courses.show', $this->course))
                ->assertSessionHas('error', 'Only students can enroll in courses.');
        }

        $this->assertSame(0, Enrollment::count());
    }

    public function test_draft_courses_cannot_be_enrolled_shown_or_learned(): void
    {
        $draft = $this->publishedCourse('Draft Course', 0, 'draft');

        $this->actingAs($this->student)->post(route('courses.enroll', $draft))->assertNotFound();
        $this->get(route('courses.show', $draft))->assertNotFound();
        $this->get(route('courses.learn', $draft))->assertNotFound();
        $this->assertSame(0, Enrollment::count());
    }

    // ---------- Course details page ----------

    public function test_the_course_page_for_a_guest(): void
    {
        $this->get(route('courses.show', $this->course))
            ->assertOk()
            ->assertSee('action="' . route('courses.enroll', $this->course) . '"', false)
            ->assertSee('Please login to enroll')
            ->assertSee('href="' . route('instructors.show', $this->instructor) . '"', false)
            // Free preview lesson links to the learning page, the locked one does not.
            ->assertSee('href="' . route('courses.learn', [$this->course, $this->freeLesson]) . '"', false)
            ->assertDontSee('href="' . route('courses.learn', [$this->course, $this->lockedLesson]) . '"', false)
            ->assertSee('30 min of video')
            ->assertSee('Certificate of completion')
            ->assertSee('Lifetime access')
            ->assertSee('0 students');
    }

    public function test_the_course_page_for_an_enrolled_student(): void
    {
        $this->enroll($this->student, $this->course);

        $this->actingAs($this->student)
            ->get(route('courses.show', $this->course))
            ->assertOk()
            ->assertSee('Go to Course')
            ->assertDontSee('Enroll Now')
            ->assertSee('href="' . route('courses.learn', [$this->course, $this->lockedLesson]) . '"', false)
            ->assertSee('1 student');
    }

    // ---------- Course Learning page ----------

    public function test_anyone_can_open_a_free_preview_lesson(): void
    {
        $this->get(route('courses.learn', [$this->course, $this->freeLesson]))
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Free preview');

        // Without a lesson in the URL, the first lesson a guest may open is shown.
        $this->get(route('courses.learn', $this->course))
            ->assertOk()
            ->assertSee('Welcome video');
    }

    public function test_a_locked_lesson_is_protected_by_the_backend(): void
    {
        $url = route('courses.learn', [$this->course, $this->lockedLesson]);

        $this->get($url)
            ->assertRedirect(route('courses.show', $this->course))
            ->assertSessionHas('error', 'Enroll in this course to open its lessons.');

        $this->actingAs($this->student)->get($url)->assertRedirect(route('courses.show', $this->course));
        $this->actingAs($this->instructor)->get($url)->assertRedirect(route('courses.show', $this->course));
    }

    public function test_an_enrolled_student_can_open_every_lesson(): void
    {
        $this->enroll($this->student, $this->course);

        $this->actingAs($this->student)
            ->get(route('courses.learn', [$this->course, $this->lockedLesson]))
            ->assertOk()
            ->assertSee('Members only')
            ->assertSee('Course progress');
    }

    public function test_a_lesson_from_another_course_is_not_found(): void
    {
        $other = $this->publishedCourse('Other Course', 0);
        $this->enroll($this->student, $other);

        $this->actingAs($this->student)
            ->get(route('courses.learn', [$other, $this->freeLesson]))
            ->assertNotFound();
    }

    // ---------- My Courses ----------

    public function test_my_courses_lists_only_the_students_own_enrollments(): void
    {
        $other = $this->publishedCourse('Not Mine', 0);
        $this->enroll($this->userWithRole('student'), $other);
        $this->enroll($this->student, $this->course);

        // Progress comes from completed lessons: 1 of the 2 lessons = 50%.
        $this->student->completedLessons()->attach($this->freeLesson->id);

        $this->actingAs($this->student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Laravel Basics')
            ->assertSee('1 of 2 lessons')
            ->assertSee('50%')
            ->assertDontSee('Not Mine')
            ->assertSee('href="' . route('courses.learn', $this->course) . '"', false);
    }

    public function test_my_courses_is_for_logged_in_students_only(): void
    {
        $this->get(route('student.courses.index'))->assertRedirect(route('login'));
        $this->actingAs($this->instructor)->get(route('student.courses.index'))->assertForbidden();
    }

    // ---------- Instructor profile ----------

    public function test_the_instructor_profile_lists_published_courses_only(): void
    {
        $this->publishedCourse('Hidden Draft', 0, 'draft');

        $this->get(route('instructors.show', $this->instructor))
            ->assertOk()
            ->assertSee('Laravel Basics')
            ->assertDontSee('Hidden Draft');

        $this->get(route('instructors.show', $this->student))->assertNotFound();
    }

    private function enroll(User $student, Course $course, int $progress = 0): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
            'progress' => $progress,
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function publishedCourse(string $title, float $price, string $status = 'published'): Course
    {
        $category = Category::firstOrCreate(['name' => 'Web']);

        return Course::create([
            'title' => $title,
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => $category->id,
            'instructor_id' => $this->instructor->id,
            'difficulty' => 'beginner',
            'price' => $price,
            'status' => $status,
        ]);
    }
}
