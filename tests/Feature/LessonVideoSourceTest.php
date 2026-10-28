<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonVideoSourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Course $course;
    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();

        $instructor = User::factory()->create();
        $instructor->forceFill(['role' => 'instructor'])->save();

        $category = Category::create(['name' => 'Web']);

        $this->course = Course::create([
            'title' => 'Course A',
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'difficulty' => 'beginner',
        ]);

        $this->section = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);
    }

    // ---------- Add Lesson ----------

    public function test_an_uploaded_video_is_saved_without_a_link(): void
    {
        $this->store([
            'video_source' => 'upload',
            'video_file' => UploadedFile::fake()->create('v.mp4', 100, 'video/mp4'),
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])->assertSessionHasNoErrors();

        $lesson = Lesson::first();
        $this->assertNotNull($lesson->video_path);
        $this->assertNull($lesson->video_url);
        Storage::disk('public')->assertExists($lesson->video_path);
    }

    public function test_a_video_link_is_saved_without_a_file(): void
    {
        $this->store([
            'video_source' => 'url',
            'video_url' => 'https://vimeo.com/76979871',
            'video_file' => UploadedFile::fake()->create('v.mp4', 100, 'video/mp4'),
        ])->assertSessionHasNoErrors();

        $lesson = Lesson::first();
        $this->assertSame('https://vimeo.com/76979871', $lesson->video_url);
        $this->assertNull($lesson->video_path);
        $this->assertSame([], Storage::disk('public')->allFiles('lessons/videos'));
    }

    public function test_a_video_lesson_needs_a_source(): void
    {
        $this->store([])->assertSessionHasErrors('video_source');
        $this->store(['video_source' => 'upload'])->assertSessionHasErrors('video_file');
        $this->store(['video_source' => 'url'])->assertSessionHasErrors('video_url');
        $this->assertSame(0, Lesson::count());
    }

    public function test_only_youtube_and_vimeo_links_are_accepted(): void
    {
        $this->store(['video_source' => 'url', 'video_url' => 'https://example.com/video.mp4'])
            ->assertSessionHasErrors(['video_url' => 'Only YouTube and Vimeo links are supported.']);

        $this->assertSame(0, Lesson::count());
    }

    public function test_other_lesson_types_do_not_need_a_video_source(): void
    {
        $this->store(['type' => 'article', 'content' => 'Text'])->assertSessionHasNoErrors();
        $this->store(['type' => 'quiz', 'title' => 'Quiz 1'])->assertSessionHasNoErrors();

        $this->assertSame(2, Lesson::count());
    }

    // ---------- Edit Lesson ----------

    public function test_editing_a_link_lesson_without_changes_keeps_the_link(): void
    {
        $lesson = $this->lesson(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->update($lesson, ['video_source' => 'url', 'video_url' => $lesson->video_url, 'title' => 'Renamed'])
            ->assertSessionHasNoErrors();

        $lesson->refresh();
        $this->assertSame('Renamed', $lesson->title);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $lesson->video_url);
    }

    public function test_switching_from_a_link_to_an_upload_removes_the_link(): void
    {
        $lesson = $this->lesson(['video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

        $this->update($lesson, [
            'video_source' => 'upload',
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'video_file' => UploadedFile::fake()->create('new.mp4', 100, 'video/mp4'),
        ])->assertSessionHasNoErrors();

        $lesson->refresh();
        $this->assertNull($lesson->video_url);
        $this->assertNotNull($lesson->video_path);
        Storage::disk('public')->assertExists($lesson->video_path);
    }

    public function test_an_old_lesson_with_both_sources_is_not_changed_until_a_source_is_chosen(): void
    {
        Storage::disk('public')->put('lessons/videos/old.mp4', 'x');
        $lesson = $this->lesson(['video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'video_path' => 'lessons/videos/old.mp4']);

        $this->update($lesson, ['title' => 'Renamed'])->assertSessionHasErrors('video_source');

        $lesson->refresh();
        $this->assertSame('Lesson', $lesson->title);
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $lesson->video_url);
        $this->assertSame('lessons/videos/old.mp4', $lesson->video_path);
        Storage::disk('public')->assertExists('lessons/videos/old.mp4');
    }

    public function test_an_old_lesson_with_both_sources_keeps_only_the_chosen_upload(): void
    {
        Storage::disk('public')->put('lessons/videos/old.mp4', 'x');
        $lesson = $this->lesson(['video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'video_path' => 'lessons/videos/old.mp4']);

        $this->update($lesson, ['video_source' => 'upload', 'video_url' => $lesson->video_url])->assertSessionHasNoErrors();

        $lesson->refresh();
        $this->assertNull($lesson->video_url);
        $this->assertSame('lessons/videos/old.mp4', $lesson->video_path);
        Storage::disk('public')->assertExists('lessons/videos/old.mp4');
    }

    // ---------- Links shown in the preview ----------

    public function test_youtube_and_vimeo_links_become_players(): void
    {
        $cases = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ?t=10' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'https://vimeo.com/76979871' => 'https://player.vimeo.com/video/76979871',
            'https://player.vimeo.com/video/76979871' => 'https://player.vimeo.com/video/76979871',
            'https://vimeo.com/channels/staffpicks/76979871' => 'https://player.vimeo.com/video/76979871',
            'https://vimeo.com/76979871/abc123def' => 'https://player.vimeo.com/video/76979871?h=abc123def',
            'https://example.com/watch?v=dQw4w9WgXcQ' => null,
            'https://evil.test/?u=https://youtu.be/dQw4w9WgXcQ' => null,
            '' => null,
        ];

        foreach ($cases as $url => $expected) {
            $this->assertSame($expected, Lesson::embedUrlFor($url), "for: $url");
        }
    }

    public function test_the_preview_shows_the_vimeo_player_and_the_two_source_notice(): void
    {
        $vimeo = $this->lesson(['video_url' => 'https://vimeo.com/76979871']);

        $this->actingAs($this->admin)
            ->get(route('admin.courses.preview', [$this->course, $vimeo]))
            ->assertOk()
            ->assertSee('https://player.vimeo.com/video/76979871', false)
            ->assertDontSee('both an uploaded video and a video link');

        $both = $this->lesson(['title' => 'Old', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'video_path' => 'lessons/videos/old.mp4']);

        $this->actingAs($this->admin)
            ->get(route('admin.courses.preview', [$this->course, $both]))
            ->assertOk()
            ->assertSee('both an uploaded video and a video link')
            ->assertSee('storage/lessons/videos/old.mp4', false);
    }

    private function store(array $changes)
    {
        return $this->actingAs($this->admin)->post(
            route('admin.courses.lessons.store', $this->course),
            array_merge(['section_id' => $this->section->id, 'title' => 'Lesson', 'type' => 'video'], $changes)
        );
    }

    private function update(Lesson $lesson, array $changes)
    {
        return $this->actingAs($this->admin)->patch(
            route('admin.courses.lessons.update', [$this->course, $lesson]),
            array_merge(['section_id' => $this->section->id, 'title' => $lesson->title, 'type' => 'video'], $changes)
        );
    }

    private function lesson(array $attributes): Lesson
    {
        return $this->section->lessons()->create(array_merge([
            'title' => 'Lesson',
            'type' => 'video',
            'order' => 1,
        ], $attributes));
    }
}
