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

class LearningProgressTest extends TestCase
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

        // 3 lessons in 2 sections: video (free), article, video.
        $this->course = $this->course('Laravel Basics');
        $intro = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);
        $more = Section::create(['course_id' => $this->course->id, 'title' => 'More', 'order' => 2]);

        $this->lessons[] = $intro->lessons()->create(['title' => 'Welcome', 'type' => 'video', 'order' => 1, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'duration_minutes' => 10, 'is_free_preview' => true]);
        $this->lessons[] = $intro->lessons()->create(['title' => 'Reading', 'type' => 'article', 'order' => 2, 'content' => "Line one\nLine two", 'reading_time_minutes' => 5, 'is_free_preview' => false]);
        $this->lessons[] = $more->lessons()->create(['title' => 'Final video', 'type' => 'video', 'order' => 1, 'video_url' => 'https://vimeo.com/76979871', 'is_free_preview' => false]);
    }

    // ---------- Mark as Complete ----------

    public function test_marking_a_lesson_complete_saves_it_and_goes_to_the_next_lesson(): void
    {
        $this->enroll();

        $this->actingAs($this->student)
            ->post($this->completeUrl(0))
            ->assertRedirect(route('courses.learn', [$this->course, $this->lessons[1]]))
            ->assertSessionHas('success', 'Lesson completed: Welcome');

        $this->assertDatabaseHas('lesson_completions', ['student_id' => $this->student->id, 'lesson_id' => $this->lessons[0]->id]);
        $this->assertSame(33, $this->enrollment()->progress); // 1 of 3, rounded down
    }

    public function test_completing_a_lesson_twice_keeps_one_record(): void
    {
        $this->enroll();

        $this->actingAs($this->student)->post($this->completeUrl(0));
        $this->actingAs($this->student)->post($this->completeUrl(0));

        $this->assertSame(1, $this->student->completedLessons()->count());
        $this->assertSame(33, $this->enrollment()->progress);
    }

    public function test_completing_the_last_lesson_completes_the_course(): void
    {
        $this->enroll();

        foreach ([0, 1] as $i) {
            $this->actingAs($this->student)->post($this->completeUrl($i));
        }

        $this->actingAs($this->student)
            ->post($this->completeUrl(2))
            ->assertRedirect(route('courses.learn', [$this->course, $this->lessons[2]]))
            ->assertSessionHas('success', 'Congratulations! You completed Laravel Basics. Your certificate is ready.');

        $enrollment = $this->enrollment();
        $this->assertSame(100, $enrollment->progress);
        $this->assertNotNull($enrollment->completed_at);
        $this->assertMatchesRegularExpression('/^LH-[A-Z0-9]{10}$/', $enrollment->certificate_code);
    }

    public function test_only_enrolled_students_can_mark_lessons_complete(): void
    {
        // Logged-in student, not enrolled.
        $this->actingAs($this->student)
            ->post($this->completeUrl(0))
            ->assertRedirect(route('courses.show', $this->course))
            ->assertSessionHas('error', 'Enroll in this course to track your progress.');

        // Guest and instructor are stopped by the route middleware.
        auth()->logout();
        $this->post($this->completeUrl(0))->assertRedirect(route('login'));
        $this->actingAs($this->instructor)->post($this->completeUrl(0))->assertForbidden();

        $this->assertDatabaseCount('lesson_completions', 0);
    }

    public function test_a_lesson_from_another_course_cannot_be_completed(): void
    {
        $other = $this->course('Other');
        $this->enroll($other);

        $this->actingAs($this->student)
            ->post(route('courses.lessons.complete', [$other, $this->lessons[0]]))
            ->assertNotFound();

        $this->assertDatabaseCount('lesson_completions', 0);
    }

    // ---------- Course Learning page ----------

    public function test_the_learning_page_shows_position_progress_and_the_complete_button(): void
    {
        $this->enroll();
        $this->student->completedLessons()->attach($this->lessons[0]->id);

        $html = $this->actingAs($this->student)
            ->get(route('courses.learn', [$this->course, $this->lessons[1]]))
            ->assertOk()
            ->assertSee('Lesson 2 of 3')
            ->assertSee('33%')
            ->assertSee('Back to My Courses')
            ->assertSee('Mark as Complete')
            ->assertSee('action="' . $this->completeUrl(1) . '"', false)
            ->assertSee('Line one<br />', false)
            ->getContent();

        // Sidebar: Welcome has a check mark, Reading is the current lesson.
        $this->assertMatchesRegularExpression('#bi-check-circle-fill[^>]*></i>\s*<span class="flex-grow-1 small">\s*<a href="[^"]+/learn/' . $this->lessons[0]->id . '"#', $html);
        $this->assertMatchesRegularExpression('#learn-lesson-current[^>]*>.*?aria-current="page"\s*>Reading<#s', $html);
    }

    public function test_a_completed_lesson_shows_completed_and_the_ends_disable_navigation(): void
    {
        $this->enroll();
        $this->student->completedLessons()->attach($this->lessons[0]->id);

        $this->actingAs($this->student)
            ->get(route('courses.learn', [$this->course, $this->lessons[0]]))
            ->assertSee('Completed ✓')
            ->assertDontSee('Mark as Complete')
            ->assertSee('<span class="btn btn-outline-primary disabled" aria-disabled="true">', false);

        $this->actingAs($this->student)
            ->get(route('courses.learn', [$this->course, $this->lessons[2]]))
            ->assertSee('<span class="btn btn-primary disabled" aria-disabled="true">', false);
    }

    public function test_continue_learning_opens_the_first_lesson_not_completed_and_records_the_visit(): void
    {
        $this->enroll();
        $this->student->completedLessons()->attach($this->lessons[0]->id);

        $this->actingAs($this->student)
            ->get(route('courses.learn', $this->course))
            ->assertOk()
            ->assertSee('<h1 class="page-title">Reading</h1>', false);

        $this->assertNotNull($this->enrollment()->last_accessed_at);
    }

    public function test_guests_can_open_free_previews_only(): void
    {
        $this->get(route('courses.learn', [$this->course, $this->lessons[0]]))
            ->assertOk()
            ->assertSee('Free preview')
            ->assertDontSee('Mark as Complete');

        $this->get(route('courses.learn', [$this->course, $this->lessons[1]]))
            ->assertRedirect(route('courses.show', $this->course));
    }

    // ---------- My Courses ----------

    public function test_my_courses_tabs_filter_by_real_progress(): void
    {
        $done = $this->course('Done Course');
        $doneLesson = Section::create(['course_id' => $done->id, 'title' => 'S', 'order' => 1])
            ->lessons()->create(['title' => 'Only', 'type' => 'article', 'order' => 1, 'content' => 'x']);

        $this->enroll();
        $this->enroll($done);
        $this->student->completedLessons()->attach([$this->lessons[0]->id, $doneLesson->id]);

        $this->actingAs($this->student)->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Laravel Basics')->assertSee('Done Course')
            ->assertSee('1 of 3 lessons')->assertSee('33%')
            ->assertSee('1 of 1 lesson')->assertSee('100%');

        $this->actingAs($this->student)->get(route('student.courses.index', ['tab' => 'in-progress']))
            ->assertSee('Laravel Basics')->assertDontSee('Done Course');

        $this->actingAs($this->student)->get(route('student.courses.index', ['tab' => 'completed']))
            ->assertSee('Done Course')->assertDontSee('Laravel Basics')
            ->assertSee('href="' . route('courses.certificate', $done) . '"', false);
    }

    public function test_the_certificate_button_is_only_shown_for_completed_courses(): void
    {
        $this->enroll();

        $this->actingAs($this->student)->get(route('student.courses.index'))
            ->assertSee('Continue Learning')
            ->assertDontSee('Download Certificate');
    }

    public function test_a_stored_progress_value_is_corrected_from_real_completions(): void
    {
        // Progress says 100, but no lesson was completed.
        $this->enroll(null, 100);

        $this->actingAs($this->student)->get(route('student.courses.index', ['tab' => 'completed']))
            ->assertSee("You haven&#039;t completed any courses yet", false);

        $this->assertSame(0, $this->enrollment()->progress);
    }

    public function test_a_course_without_lessons_is_zero_percent(): void
    {
        $empty = $this->course('Empty Course');
        $this->enroll($empty);

        $this->actingAs($this->student)->get(route('student.courses.index', ['tab' => 'in-progress']))
            ->assertOk()
            ->assertSee('Empty Course')
            ->assertSee('0 of 0 lessons');
    }

    public function test_the_tab_is_kept_in_the_page_links(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            $this->enroll($this->course("Course $i"));
        }

        $this->actingAs($this->student)->get(route('student.courses.index', ['tab' => 'in-progress']))
            ->assertSee('tab=in-progress&amp;page=2', false);
    }

    public function test_my_courses_does_not_run_more_queries_for_more_courses(): void
    {
        $countQueries = function () {
            $count = 0;
            \Illuminate\Support\Facades\DB::listen(function () use (&$count) {
                $count++;
            });

            $this->actingAs($this->student)->get(route('student.courses.index'))->assertOk();

            return $count;
        };

        $this->enroll();
        $this->enroll($this->course('Second'));
        $withTwo = $countQueries();

        for ($i = 1; $i <= 6; $i++) {
            $this->enroll($this->course("Extra $i"));
        }
        $withEight = $countQueries();

        $this->assertSame($withTwo, $withEight, 'My Courses should not run one query per course (N+1).');
    }

    public function test_empty_states(): void
    {
        $this->actingAs($this->student)->get(route('student.courses.index'))
            ->assertSee("You haven&#039;t enrolled in any courses yet", false)
            ->assertSee('Browse Courses');
    }

    // ---------- Certificate ----------

    public function test_the_certificate_is_available_only_after_completing_the_course(): void
    {
        $this->enroll();
        $url = route('courses.certificate', $this->course);

        $this->actingAs($this->student)->get($url)->assertForbidden();

        $this->student->completedLessons()->attach(collect($this->lessons)->pluck('id'));

        // A real PDF file, sent as a download (not a page in the browser).
        $response = $this->actingAs($this->student)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('LearnHub-Certificate-Laravel-Basics.pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());

        // What goes into the PDF (dompdf compresses the file itself, so check the template).
        $enrollment = $this->enrollment();
        $html = view('certificates.pdf', [
            'course' => $this->course->load('instructor'),
            'enrollment' => $enrollment,
            'student' => $this->student,
        ])->render();

        foreach ([$this->student->name, 'Laravel Basics', $this->instructor->name, 'CERTIFICATE OF COMPLETION', now()->format('F j, Y'), $enrollment->certificate_code, '#16423C'] as $text) {
            $this->assertStringContainsString(e($text), $html);
        }
    }

    public function test_the_certificate_file_name_is_cleaned_up(): void
    {
        $course = $this->course('C++ & Web: Part 1!');
        $lesson = Section::create(['course_id' => $course->id, 'title' => 'S', 'order' => 1])
            ->lessons()->create(['title' => 'Only', 'type' => 'article', 'order' => 1, 'content' => 'x']);

        $this->enroll($course);
        $this->student->completedLessons()->attach($lesson->id);

        $this->actingAs($this->student)
            ->get(route('courses.certificate', $course))
            ->assertDownload('LearnHub-Certificate-C-Web-Part-1.pdf');
    }

    public function test_the_certificate_is_protected(): void
    {
        $url = route('courses.certificate', $this->course);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->student)->get($url)->assertForbidden(); // not enrolled
        $this->actingAs($this->instructor)->get($url)->assertForbidden();
    }

    // ---------- Helpers ----------

    private function completeUrl(int $index): string
    {
        return route('courses.lessons.complete', [$this->course, $this->lessons[$index]]);
    }

    private function enroll(?Course $course = null, int $progress = 0): Enrollment
    {
        return Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => ($course ?? $this->course)->id,
            'enrolled_at' => now(),
            'progress' => $progress,
            'status' => 'active',
        ]);
    }

    private function enrollment(): Enrollment
    {
        return Enrollment::where('student_id', $this->student->id)
            ->where('course_id', $this->course->id)
            ->sole();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function course(string $title): Course
    {
        return Course::create([
            'title' => $title,
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => Category::firstOrCreate(['name' => 'Web'])->id,
            'instructor_id' => $this->instructor->id,
            'difficulty' => 'beginner',
            'price' => 0,
            'status' => 'published',
        ]);
    }
}
