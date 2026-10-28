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

class CourseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private Course $course;
    private array $lessonIds;

    /** @var array<string, User> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = $this->userWithRole('instructor');
        [$this->course, $this->lessonIds] = $this->courseWithLessons('Laravel Basics', 2);

        // Amal: completed (2/2), opened 2 days ago   -> active, completed
        // Bilal: 1/2, opened today                    -> active, in progress
        // Carla: 0/2, never opened                    -> inactive, in progress
        // Dana: 1/2, opened 10 days ago               -> inactive, in progress
        $this->students['Amal'] = $this->student('Amal Student', 'amal@example.com', [0, 1], now()->subDays(2));
        $this->students['Bilal'] = $this->student('Bilal Student', 'bilal@example.com', [0], now());
        $this->students['Carla'] = $this->student('Carla Student', 'carla@example.com', [], null);
        $this->students['Dana'] = $this->student('Dana Student', 'dana@example.com', [1], now()->subDays(10));
    }

    // ---------- The 4 numbers ----------

    public function test_the_four_numbers_come_from_real_data(): void
    {
        $response = $this->analytics()->assertOk();

        // Total enrollments, active (7 days), completion rate, average progress
        $this->assertSame(['4', '2', '25%', '50%'], $this->statValues($response));
        $response->assertSee('1 of 4 completed');
    }

    public function test_progress_is_recalculated_from_completed_lessons(): void
    {
        // Wrong stored values: the page must not trust them.
        Enrollment::query()->update(['progress' => 100]);

        $this->assertSame(['4', '2', '25%', '50%'], $this->statValues($this->analytics()));
    }

    public function test_a_lesson_added_later_lowers_progress_and_completion(): void
    {
        Section::first()->lessons()->create(['title' => 'New lesson', 'type' => 'article', 'order' => 3, 'content' => 'x']);

        // Amal 2/3 = 66, Bilal 1/3 = 33, Carla 0, Dana 33 -> nobody completed, average 33.
        $this->assertSame(['4', '2', '0%', '33%'], $this->statValues($this->analytics()));
    }

    public function test_a_course_without_students(): void
    {
        [$empty] = $this->courseWithLessons('Empty Course', 1);

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.analytics', $empty))
            ->assertOk()
            ->assertSee('No students enrolled yet')
            ->assertSee('No students yet');

        $this->assertSame(['0', '0', '—', '—'], $this->statValues(
            $this->actingAs($this->instructor)->get(route('instructor.courses.analytics', $empty))
        ));
    }

    // ---------- Student table, filters, search, pagination ----------

    public function test_the_student_table(): void
    {
        $html = $this->analytics()->getContent();

        foreach (['Amal Student', 'bilal@example.com', 'Carla Student', 'Dana Student'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        $this->assertMatchesRegularExpression('#Amal Student.*?<span class="small fw-semibold">100%</span>#s', $html);
        $this->assertMatchesRegularExpression('#Carla Student.*?<span class="small fw-semibold">0%</span>.*?Never#s', $html);
        $this->assertStringContainsString(now()->format('M j, Y'), $html); // enrollment date
    }

    public function test_the_four_filters_and_their_counts(): void
    {
        $this->assertSame(['Amal', 'Bilal', 'Carla', 'Dana'], $this->listed([]));
        $this->assertSame(['Bilal', 'Carla', 'Dana'], $this->listed(['status' => 'in-progress']));
        $this->assertSame(['Amal'], $this->listed(['status' => 'completed']));
        $this->assertSame(['Carla', 'Dana'], $this->listed(['status' => 'inactive']));

        // Unknown filter = All
        $this->assertSame(['Amal', 'Bilal', 'Carla', 'Dana'], $this->listed(['status' => 'whatever']));

        $this->assertSame(
            ['all' => 4, 'in-progress' => 3, 'completed' => 1, 'inactive' => 2],
            $this->analytics()->viewData('counts')
        );
    }

    public function test_search_by_name_or_email_works_with_filters(): void
    {
        $this->assertSame(['Bilal'], $this->listed(['search' => 'bilal']));
        $this->assertSame(['Dana'], $this->listed(['search' => 'dana@example']));
        $this->assertSame([], $this->listed(['search' => 'amal', 'status' => 'inactive']));

        $this->analytics(['search' => 'nobody'])->assertSee('No students found');

        // The filter links keep the search, the search form keeps the filter.
        $this->analytics(['search' => 'student', 'status' => 'completed'])
            ->assertSee('status=inactive&amp;search=student', false)
            ->assertSee('<input type="hidden" name="status" value="completed">', false);
    }

    public function test_pagination_keeps_the_filter_and_search(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->student("Extra $i", "extra$i@example.com", [], null);
        }

        $response = $this->analytics(['status' => 'inactive', 'search' => 'extra']);

        $this->assertCount(15, $response->viewData('enrollments'));
        $response->assertSee('status=inactive&amp;search=extra&amp;page=2', false);
    }

    // ---------- Access ----------

    public function test_only_the_owning_instructor_can_open_the_analytics(): void
    {
        $url = route('instructor.courses.analytics', $this->course);

        $this->actingAs($this->userWithRole('instructor'))->get($url)->assertForbidden();
        $this->actingAs($this->students['Amal'])->get($url)->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    // ---------- Entry points ----------

    public function test_the_dashboard_shows_the_numbers_and_the_analytics_button(): void
    {
        [$empty] = $this->courseWithLessons('Empty Course', 1);

        $html = $this->actingAs($this->instructor)->get('/instructor/dashboard')->assertOk()->getContent();

        // Enrollments, Active, Completion rate, Avg. progress
        $this->assertSame(['4', '2', '25%', '50%'], $this->dashboardRow($html, 'Laravel Basics'));
        $this->assertSame(['0', '0', '—', '—'], $this->dashboardRow($html, 'Empty Course'));

        $this->assertStringContainsString('href="' . route('instructor.courses.analytics', $this->course) . '"', $html);
        $this->assertStringContainsString('href="' . route('instructor.courses.analytics', $empty) . '"', $html);
    }

    public function test_the_manage_page_has_the_analytics_button_for_instructors_only(): void
    {
        $analyticsUrl = route('instructor.courses.analytics', $this->course);

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.content', $this->course))
            ->assertOk()
            ->assertSee('href="' . $analyticsUrl . '"', false);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.courses.content', $this->course))
            ->assertOk()
            ->assertDontSee('/analytics', false);
    }

    public function test_the_dashboard_does_not_run_more_queries_for_more_courses(): void
    {
        $count = function () {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->actingAs($this->instructor)->get('/instructor/dashboard')->assertOk();

            return $queries;
        };

        // The first visit also saves the corrected progress (UPDATE queries); measure after it.
        $count();
        $withOne = $count();

        for ($i = 1; $i <= 5; $i++) {
            [$course] = $this->courseWithLessons("Course $i", 2);
            Enrollment::create(['student_id' => $this->students['Bilal']->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        }

        $count();
        $this->assertSame($withOne, $count(), 'The dashboard should not run one query per course (N+1).');
    }

    // ---------- Helpers ----------

    private function analytics(array $query = [])
    {
        return $this->actingAs($this->instructor)
            ->get(route('instructor.courses.analytics', array_merge(['course' => $this->course], $query)));
    }

    /**
     * First names of the students listed for this query, sorted.
     */
    private function listed(array $query): array
    {
        return $this->analytics($query)
            ->viewData('enrollments')
            ->map(fn ($enrollment) => explode(' ', $enrollment->student->name)[0])
            ->sort()
            ->values()
            ->all();
    }

    private function statValues($response): array
    {
        preg_match_all('#<div class="stat-value">\s*(.*?)\s*</div>#s', $response->getContent(), $matches);

        return $matches[1];
    }

    /**
     * Enrollments, Active, Completion rate and Avg. progress cells for one course row.
     */
    private function dashboardRow(string $html, string $title): array
    {
        preg_match('#' . preg_quote($title, '#') . '\s*</td>.*?</td>\s*((?:<td class="text-end">.*?</td>\s*){4})#s', $html, $row);
        preg_match_all('#<td class="text-end">\s*(.*?)\s*</td>#s', $row[1] ?? '', $cells);

        return $cells[1];
    }

    private function student(string $name, string $email, array $completedLessons, $lastAccessedAt): User
    {
        $student = User::factory()->create(['name' => $name, 'email' => $email]);
        $student->forceFill(['role' => 'student'])->save();

        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $this->course->id,
            'enrolled_at' => now(),
            'last_accessed_at' => $lastAccessedAt,
        ]);

        $student->completedLessons()->attach(array_map(fn ($i) => $this->lessonIds[$i], $completedLessons));

        return $student;
    }

    private function courseWithLessons(string $title, int $lessons): array
    {
        $course = Course::create([
            'title' => $title,
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => Category::firstOrCreate(['name' => 'Web'])->id,
            'instructor_id' => $this->instructor->id,
            'difficulty' => 'beginner',
            'price' => 0,
            'status' => 'published',
        ]);

        $section = Section::create(['course_id' => $course->id, 'title' => 'S', 'order' => 1]);

        $ids = [];
        for ($i = 1; $i <= $lessons; $i++) {
            $ids[] = $section->lessons()->create(['title' => "Lesson $i", 'type' => 'article', 'order' => $i, 'content' => 'x'])->id;
        }

        return [$course, $ids];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
