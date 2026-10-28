<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonFileRemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;
    private Course $course;
    private Section $section;
    private Lesson $lesson;
    private LessonResource $slides;
    private LessonResource $code;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = $this->userWithRole('admin');
        $this->instructor = $this->userWithRole('instructor');

        $this->course = $this->courseFor($this->instructor, 'Course A');
        $this->section = Section::create(['course_id' => $this->course->id, 'title' => 'Intro', 'order' => 1]);

        $this->lesson = $this->section->lessons()->create([
            'title' => 'Lesson 1',
            'type' => 'video',
            'order' => 1,
            'video_path' => $this->storedFile('lessons/videos/old.mp4'),
            'video_thumbnail' => $this->storedFile('lessons/thumbnails/old.jpg'),
        ]);

        $this->slides = $this->resourceFor($this->lesson, 'slides.pdf');
        $this->code = $this->resourceFor($this->lesson, 'code.zip');
    }

    public function test_saving_without_changes_keeps_every_file(): void
    {
        // No video URL and no new upload: the existing uploaded video is enough.
        $this->updateAs($this->admin, [])->assertRedirect()->assertSessionHasNoErrors();

        $this->lesson->refresh();
        $this->assertSame('lessons/videos/old.mp4', $this->lesson->video_path);
        $this->assertSame('lessons/thumbnails/old.jpg', $this->lesson->video_thumbnail);
        Storage::disk('public')->assertExists(['lessons/videos/old.mp4', 'lessons/thumbnails/old.jpg', $this->slides->file_path, $this->code->file_path]);
        $this->assertSame(2, $this->lesson->resources()->count());
    }

    public function test_switching_to_a_video_link_removes_the_uploaded_video(): void
    {
        $this->updateAs($this->admin, ['video_source' => 'url', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'])
            ->assertSessionHasNoErrors();

        $this->lesson->refresh();
        $this->assertNull($this->lesson->video_path);
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $this->lesson->video_url);
        Storage::disk('public')->assertMissing('lessons/videos/old.mp4');
        Storage::disk('public')->assertExists(['lessons/thumbnails/old.jpg', $this->slides->file_path, $this->code->file_path]);
    }

    public function test_removing_the_only_video_source_fails_validation_and_keeps_the_file(): void
    {
        $this->updateAs($this->admin, ['remove_video_file' => '1'])->assertSessionHasErrors('video_file');

        $this->assertSame('lessons/videos/old.mp4', $this->lesson->refresh()->video_path);
        Storage::disk('public')->assertExists('lessons/videos/old.mp4');
    }

    public function test_the_thumbnail_can_be_removed(): void
    {
        $this->updateAs($this->admin, ['remove_video_thumbnail' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($this->lesson->refresh()->video_thumbnail);
        Storage::disk('public')->assertMissing('lessons/thumbnails/old.jpg');
        Storage::disk('public')->assertExists('lessons/videos/old.mp4');
    }

    public function test_one_resource_can_be_removed_without_touching_the_others(): void
    {
        $this->updateAs($this->admin, ['remove_resources' => [$this->slides->id]])->assertSessionHasNoErrors();

        $this->assertModelMissing($this->slides);
        $this->assertModelExists($this->code);
        Storage::disk('public')->assertMissing($this->slides->file_path);
        Storage::disk('public')->assertExists($this->code->file_path);
    }

    public function test_a_resource_of_another_lesson_cannot_be_removed(): void
    {
        $otherLesson = $this->section->lessons()->create(['title' => 'Lesson 2', 'type' => 'article', 'content' => 'x', 'order' => 2]);
        $otherResource = $this->resourceFor($otherLesson, 'other.pdf');

        $this->updateAs($this->admin, ['remove_resources' => [$otherResource->id]])
            ->assertSessionHasErrors('remove_resources.0');

        $this->assertModelExists($otherResource);
        Storage::disk('public')->assertExists($otherResource->file_path);
        $this->assertSame(2, $this->lesson->resources()->count());
    }

    public function test_an_instructor_cannot_remove_files_from_another_instructors_course(): void
    {
        $otherInstructor = $this->userWithRole('instructor');

        $this->actingAs($otherInstructor)
            ->patch(route('instructor.courses.lessons.update', [$this->course, $this->lesson]), $this->payload([
                'remove_video_thumbnail' => '1',
                'remove_resources' => [$this->slides->id],
            ]))
            ->assertForbidden();

        $this->assertSame('lessons/thumbnails/old.jpg', $this->lesson->refresh()->video_thumbnail);
        Storage::disk('public')->assertExists(['lessons/thumbnails/old.jpg', $this->slides->file_path]);
    }

    public function test_a_lesson_cannot_be_edited_through_another_course(): void
    {
        $otherCourse = $this->courseFor($this->instructor, 'Course B');

        $this->updateAs($this->admin, ['remove_video_thumbnail' => '1'], $otherCourse)->assertNotFound();

        Storage::disk('public')->assertExists('lessons/thumbnails/old.jpg');
    }

    public function test_the_owning_instructor_can_remove_files(): void
    {
        $this->actingAs($this->instructor)
            ->patch(route('instructor.courses.lessons.update', [$this->course, $this->lesson]), $this->payload([
                'remove_video_thumbnail' => '1',
                'remove_resources' => [$this->code->id],
            ]))
            ->assertRedirect(route('instructor.courses.content', $this->course))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->lesson->refresh()->video_thumbnail);
        $this->assertModelMissing($this->code);
        $this->assertModelExists($this->slides);
    }

    public function test_uploads_still_replace_the_video_and_add_resources(): void
    {
        $this->updateAs($this->admin, [
            // A new upload wins over the remove checkbox: the video is replaced, not cleared.
            'remove_video_file' => '1',
            'video_file' => UploadedFile::fake()->create('new.mp4', 200, 'video/mp4'),
            'resources' => [UploadedFile::fake()->create('extra.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $this->lesson->refresh();
        $this->assertNotNull($this->lesson->video_path);
        $this->assertNotSame('lessons/videos/old.mp4', $this->lesson->video_path);
        Storage::disk('public')->assertExists($this->lesson->video_path);
        Storage::disk('public')->assertMissing('lessons/videos/old.mp4');
        $this->assertSame(3, $this->lesson->resources()->count());
    }

    private function updateAs(User $user, array $changes, ?Course $course = null)
    {
        return $this->actingAs($user)->patch(
            route('admin.courses.lessons.update', [$course ?? $this->course, $this->lesson]),
            $this->payload($changes)
        );
    }

    private function payload(array $changes): array
    {
        return array_merge([
            'section_id' => $this->section->id,
            'title' => 'Lesson 1',
            'type' => 'video',
            'video_source' => 'upload',
        ], $changes);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function courseFor(User $instructor, string $title): Course
    {
        $category = Category::create(['name' => 'Category for ' . $title]);

        return Course::create([
            'title' => $title,
            'short_description' => 'Short',
            'description' => 'Full',
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'difficulty' => 'beginner',
        ]);
    }

    private function resourceFor(Lesson $lesson, string $name): LessonResource
    {
        return $lesson->resources()->create([
            'original_name' => $name,
            'file_path' => $this->storedFile('lessons/resources/' . $lesson->id . '-' . $name),
            'file_size' => 10,
        ]);
    }

    private function storedFile(string $path): string
    {
        Storage::disk('public')->put($path, 'test');

        return $path;
    }
}
