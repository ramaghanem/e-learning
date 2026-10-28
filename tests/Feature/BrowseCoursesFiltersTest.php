<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowseCoursesFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private Category $web;
    private Category $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::factory()->create(['name' => 'Sara Teacher']);
        $this->instructor->forceFill(['role' => 'instructor'])->save();

        $this->web = Category::create(['name' => 'Web']);
        $this->data = Category::create(['name' => 'Data']);

        $this->course('Free Beginner Web', $this->web, 'beginner', 0);
        $this->course('Cheap Intermediate Web', $this->web, 'intermediate', 15);
        $this->course('Pricey Advanced Data', $this->data, 'advanced', 87.5);
        $this->course('Free Advanced Data', $this->data, 'advanced', 0);

        // A draft that matches every filter: it must never be listed.
        $this->course('Draft Free Beginner Web', $this->web, 'beginner', 0, 'draft');
    }

    public function test_without_filters_only_published_courses_are_listed(): void
    {
        $this->assertTitles([], [
            'Free Beginner Web', 'Cheap Intermediate Web', 'Pricey Advanced Data', 'Free Advanced Data',
        ]);
    }

    public function test_one_or_more_difficulties(): void
    {
        $this->assertTitles(['difficulty' => ['advanced']], ['Pricey Advanced Data', 'Free Advanced Data']);
        $this->assertTitles(
            ['difficulty' => ['beginner', 'intermediate']],
            ['Free Beginner Web', 'Cheap Intermediate Web']
        );
    }

    public function test_unknown_difficulty_values_are_ignored(): void
    {
        $this->assertTitles(['difficulty' => ['expert']], [
            'Free Beginner Web', 'Cheap Intermediate Web', 'Pricey Advanced Data', 'Free Advanced Data',
        ]);
    }

    public function test_free_only(): void
    {
        $this->assertTitles(['free_only' => '1'], ['Free Beginner Web', 'Free Advanced Data']);
    }

    public function test_maximum_price(): void
    {
        $this->assertTitles(['max_price' => '15'], ['Free Beginner Web', 'Cheap Intermediate Web', 'Free Advanced Data']);
        $this->assertTitles(['max_price' => '0'], ['Free Beginner Web', 'Free Advanced Data']);
    }

    public function test_the_top_of_the_slider_means_any_price(): void
    {
        // Highest published price is 87.50, so the slider goes up to 90.
        $this->get(route('courses.index'))
            ->assertOk()
            ->assertSee('max="90"', false)
            ->assertSee('Any price');

        $this->assertTitles(['max_price' => '90'], [
            'Free Beginner Web', 'Cheap Intermediate Web', 'Pricey Advanced Data', 'Free Advanced Data',
        ]);
    }

    public function test_filters_work_together(): void
    {
        $this->assertTitles(
            ['category' => [$this->data->id], 'difficulty' => ['advanced'], 'free_only' => '1'],
            ['Free Advanced Data']
        );

        $this->assertTitles(
            ['category' => [$this->web->id, $this->data->id], 'difficulty' => ['intermediate', 'advanced'], 'max_price' => '50'],
            ['Cheap Intermediate Web', 'Free Advanced Data']
        );
    }

    public function test_search_cannot_bring_back_draft_courses(): void
    {
        // "Web" matches a draft title, and the instructor name matches every course.
        $this->assertTitles(['search' => 'Web'], ['Free Beginner Web', 'Cheap Intermediate Web']);
        $this->assertTitles(['search' => 'Sara', 'free_only' => '1'], ['Free Beginner Web', 'Free Advanced Data']);
    }

    public function test_chosen_filters_stay_selected_and_are_kept_in_page_links(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->course("Extra $i", $this->web, 'beginner', 0);
        }

        $response = $this->get(route('courses.index', ['difficulty' => ['beginner'], 'free_only' => '1']))
            ->assertOk();

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('#Showing 1–12 of 13\s+courses#', $html);
        $this->assertMatchesRegularExpression('#value="beginner"\s+class="form-check-input"\s+checked#', $html);
        $this->assertMatchesRegularExpression('#name="free_only"\s+value="1"\s+class="form-check-input"\s+checked#', $html);
        $this->assertStringContainsString('difficulty%5B0%5D=beginner', $html);
        $this->assertStringContainsString('free_only=1', $html);

        $this->get(route('courses.index', ['max_price' => '40']))
            ->assertSee('value="40"', false)
            ->assertSee('$40');
    }

    private function assertTitles(array $query, array $expected): void
    {
        $listed = $this->get(route('courses.index', $query))
            ->assertOk()
            ->viewData('courses')
            ->pluck('title')
            ->sort()
            ->values()
            ->all();

        sort($expected);

        $this->assertSame($expected, $listed, 'for query: ' . http_build_query($query));
    }

    private function course(string $title, Category $category, string $difficulty, float $price, string $status = 'published'): Course
    {
        return Course::create([
            'title' => $title,
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => $category->id,
            'instructor_id' => $this->instructor->id,
            'difficulty' => $difficulty,
            'price' => $price,
            'status' => $status,
        ]);
    }
}
