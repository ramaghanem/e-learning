<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentStatisticsTest extends TestCase
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

    public function test_a_student_without_activity_sees_real_zeros(): void
    {
        $response = $this->statistics()->assertOk();

        // Enrolled, In progress, Completed, Learning time, Certificates
        $this->assertSame(['0', '0', '0', '0m', '0'], $this->statValues($response));
        $response->assertSee('No completed lessons yet');
    }

    public function test_the_numbers_match_the_dashboard(): void
    {
        [$done, $doneLessons] = $this->course('Done Course', [['video', 30]]);
        [$busy, $busyLessons] = $this->course('Busy Course', [['video', 10], ['article', 5]]);
        $this->enroll($done);
        $this->enroll($busy);
        $this->complete($doneLessons[0]);
        $this->complete($busyLessons[0]);

        $statistics = $this->statValues($this->statistics());
        $dashboard = $this->dashboardValues($this->actingAs($this->student)->get('/student/dashboard'));

        // Enrolled, In progress, Completed, Certificates must be the same on both pages.
        $this->assertSame(['2', '1', '1', '1'], [$statistics[0], $statistics[1], $statistics[2], $statistics[4]]);
        $this->assertSame($dashboard, [$statistics[0], $statistics[1], $statistics[2], $statistics[4]]);
    }

    public function test_learning_time_adds_up_the_completed_lessons_only(): void
    {
        [$course, $lessons] = $this->course('Laravel Basics', [
            ['video', 30],      // completed
            ['article', 15],    // completed
            ['quiz', null],     // completed, no duration
            ['video', null],    // completed, no duration set
            ['video', 60],      // NOT completed
        ]);
        $this->enroll($course);

        foreach ([0, 1, 2, 3] as $i) {
            $this->complete($lessons[$i]);
        }

        $this->assertSame('45m', $this->statValues($this->statistics())[3]);
    }

    public function test_learning_time_shows_hours_and_minutes(): void
    {
        [$course, $lessons] = $this->course('Long Course', [['video', 50], ['article', 25]]);
        $this->enroll($course);
        $this->complete($lessons[0]);
        $this->complete($lessons[1]);

        $this->assertSame('1h 15m', $this->statValues($this->statistics())[3]);
    }

    public function test_draft_courses_are_not_counted(): void
    {
        [$draft, $lessons] = $this->course('Hidden Draft', [['video', 40]], 'draft');
        $this->enroll($draft);
        $this->complete($lessons[0]);

        $response = $this->statistics();

        $this->assertSame(['0', '0', '0', '0m', '0'], $this->statValues($response));
        $response->assertDontSee('Hidden Draft');
    }

    public function test_recent_activity_shows_the_last_10_completed_lessons_newest_first(): void
    {
        $spec = array_fill(0, 12, ['article', 5]);
        [$course, $lessons] = $this->course('Laravel Basics', $spec);
        $this->enroll($course);

        foreach ($lessons as $i => $lesson) {
            $this->complete($lesson, now()->subHours(12 - $i)); // lesson 12 is the newest
        }

        $response = $this->statistics();
        $listed = $response->viewData('recentLessons')->pluck('title')->all();

        $this->assertSame(['Lesson 12', 'Lesson 11', 'Lesson 10', 'Lesson 9', 'Lesson 8', 'Lesson 7', 'Lesson 6', 'Lesson 5', 'Lesson 4', 'Lesson 3'], $listed);
        $response->assertSeeInOrder(['Lesson 12', 'Laravel Basics', '1 hour ago', 'Lesson 11'])
            ->assertSee('href="' . route('courses.learn', [$course, $lessons[11]]) . '"', false)
            ->assertDontSee('>Lesson 2<', false);
    }

    public function test_a_student_only_sees_their_own_statistics(): void
    {
        [$course, $lessons] = $this->course('Someone Else', [['video', 30]]);
        $other = $this->userWithRole('student');
        Enrollment::create(['student_id' => $other->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        $other->completedLessons()->attach($lessons[0]->id);

        $response = $this->statistics();

        $this->assertSame(['0', '0', '0', '0m', '0'], $this->statValues($response));
        $response->assertDontSee('Someone Else');
    }

    public function test_the_page_is_for_logged_in_students_only(): void
    {
        $this->get(route('student.statistics'))->assertRedirect(route('login'));
        $this->actingAs($this->instructor)->get(route('student.statistics'))->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))->get(route('student.statistics'))->assertForbidden();
    }

    public function test_students_reach_it_from_the_user_menu_and_the_dashboard(): void
    {
        $url = route('student.statistics');

        $this->actingAs($this->student)->get('/student/dashboard')
            ->assertSee('<a class="dropdown-item" href="' . $url . '">', false)                 // user menu
            ->assertSee('<a href="' . $url . '" class="btn btn-outline-light">', false);        // dashboard header

        $this->actingAs($this->instructor)->get('/instructor/dashboard')
            ->assertDontSee('My Statistics');
    }

    public function test_the_page_focuses_on_learning_time_and_activity_without_repeating_the_dashboard_cards(): void
    {
        $response = $this->statistics()->assertOk();

        // Only one big number (the learning time), not the dashboard's stat cards.
        $this->assertSame(1, substr_count($response->getContent(), 'class="stat-value'));
        $response->assertDontSee('class="stat-card', false)
            ->assertSee('Total learning time')
            ->assertSee('Recent Activity');

        // The learning time panel has no "from N completed lessons" line.
        preg_match('#Total learning time(.*?)Recent Activity#s', $response->getContent(), $panel);
        $this->assertStringNotContainsString('lesson', $panel[1]);
    }

    public function test_the_summary_numbers_link_to_the_my_courses_tabs(): void
    {
        [$course, $lessons] = $this->course('Done Course', [['video', 30]]);
        $this->enroll($course);
        $this->complete($lessons[0]);

        $this->statistics()
            ->assertSeeText('1 enrolled')
            ->assertSeeText('0 in progress')
            ->assertSeeText('1 completed')
            ->assertSeeText('1 certificate')
            ->assertSee('href="' . route('student.courses.index') . '"', false)
            ->assertSee('href="' . route('student.courses.index', ['tab' => 'in-progress']) . '"', false)
            ->assertSee('href="' . route('student.courses.index', ['tab' => 'completed']) . '"', false);
    }

    public function test_recent_activity_does_not_run_one_query_per_lesson(): void
    {
        [$course, $lessons] = $this->course('Laravel Basics', array_fill(0, 10, ['video', 5]));
        $this->enroll($course);

        $count = function () {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->statistics()->assertOk();

            return $queries;
        };

        $this->complete($lessons[0]);
        $this->complete($lessons[1]);
        $count();                       // first visit also saves the progress
        $withTwo = $count();

        foreach (array_slice($lessons, 2) as $lesson) {
            $this->complete($lesson);
        }
        $count();
        $this->assertSame($withTwo, $count(), 'Recent activity should not run one query per lesson (N+1).');
    }

    // ---------- Helpers ----------

    private function statistics()
    {
        return $this->actingAs($this->student)->get(route('student.statistics'));
    }

    /**
     * The statistics page numbers, in order: enrolled, in progress, completed, learning time, certificates.
     */
    private function statValues($response): array
    {
        $html = $response->getContent();

        preg_match('#id="learningTime">\s*(.*?)\s*</div>#s', $html, $time);
        preg_match_all('#<strong class="text-body">\s*(\d+)\s*</strong>#', $html, $numbers);

        [$enrolled, $inProgress, $completed, $certificates] = $numbers[1] + [null, null, null, null];

        return [$enrolled, $inProgress, $completed, $time[1] ?? null, $certificates];
    }

    /**
     * The 4 stat cards of the student dashboard.
     */
    private function dashboardValues($response): array
    {
        preg_match_all('#<div class="stat-value">\s*(.*?)\s*</div>#s', $response->getContent(), $matches);

        return $matches[1];
    }

    /**
     * @param  array  $lessons  [[type, minutes], ...]
     */
    private function course(string $title, array $lessons, string $status = 'published'): array
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

        $created = [];
        foreach ($lessons as $i => [$type, $minutes]) {
            $created[] = $section->lessons()->create([
                'title' => 'Lesson ' . ($i + 1),
                'type' => $type,
                'order' => $i + 1,
                'content' => 'x',
                'duration_minutes' => $type === 'video' ? $minutes : null,
                'reading_time_minutes' => $type === 'article' ? $minutes : null,
            ]);
        }

        return [$course, $created];
    }

    private function enroll(Course $course): void
    {
        Enrollment::create(['student_id' => $this->student->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
    }

    private function complete($lesson, $at = null): void
    {
        $at ??= now();
        $this->student->completedLessons()->attach($lesson->id, ['created_at' => $at, 'updated_at' => $at]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
