<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

abstract class CourseLessonController extends Controller
{
    abstract protected function authorizeCourse(Course $course): void;

    abstract protected function routePrefix(): string;

    private function contentRoute(Course $course): string
    {
        return $this->routePrefix() . '.courses.content';
    }

    public function create(Course $course)
    {
        $this->authorizeCourse($course);

        $sections = $course->sections()
            ->orderBy('order')
            ->get();

        abort_if($sections->isEmpty(), 422, 'Add a section before creating a lesson.');

        return view(
            $this->routePrefix() . '.courses.lessons.create',
            compact('course', 'sections')
        );
    }

    public function edit(Course $course, Lesson $lesson)
    {
        $this->authorizeCourse($course);
        $this->ensureLessonBelongsToCourse($course, $lesson);

        $sections = $course->sections()
            ->orderBy('order')
            ->get();

        return view(
            $this->routePrefix() . '.courses.lessons.edit',
            compact('course', 'lesson', 'sections')
        );
    }

    public function store(Request $request, Course $course)
    {
        $this->authorizeCourse($course);

        $data = $this->validateLesson($request, $course);

        $section = $course->sections()->findOrFail($data['section_id']);

        $data['order'] = ((int) $section->lessons()->max('order')) + 1;

        $lesson = $section->lessons()->create($data);

        $this->saveUploads($request, $lesson);

        return redirect()
            ->route($this->contentRoute($course), $course)
            ->with('success', 'Lesson added successfully.');
    }

    public function update(
        Request $request,
        Course $course,
        Lesson $lesson
    ) {
        $this->authorizeCourse($course);
        $this->ensureLessonBelongsToCourse($course, $lesson);

        $data = $this->validateLesson($request, $course, $lesson);
        $removals = $this->validateRemovals($request, $lesson);

        $newSection = $course->sections()->findOrFail($data['section_id']);

        if ((int) $lesson->section_id !== (int) $newSection->id) {
            $data['order'] = ((int) $newSection->lessons()->max('order')) + 1;
        }

        // Switching a video lesson to a YouTube/Vimeo link detaches the uploaded file.
        $detachedVideo = array_key_exists('video_path', $data) ? $lesson->video_path : null;

        // Update the database first. The removed files are only deleted
        // from disk after the transaction has committed successfully.
        $filesToDelete = DB::transaction(function () use ($request, $lesson, $data, $removals) {
            $lesson->update($data);

            return $this->applyRemovals($request, $lesson, $removals);
        });

        if ($detachedVideo) {
            $filesToDelete[] = $detachedVideo;
        }

        Storage::disk('public')->delete($filesToDelete);

        $this->saveUploads($request, $lesson);

        return redirect()
            ->route($this->contentRoute($course), $course)
            ->with('success', 'Lesson updated successfully.');
    }

    public function destroy(Course $course, Lesson $lesson)
    {
        $this->authorizeCourse($course);
        $this->ensureLessonBelongsToCourse($course, $lesson);

        DB::transaction(function () use ($lesson) {
            foreach ($lesson->resources as $resource) {
                Storage::disk('public')->delete($resource->file_path);
                $resource->delete();
            }

            if ($lesson->video_path) {
                Storage::disk('public')->delete($lesson->video_path);
            }

            if ($lesson->video_thumbnail) {
                Storage::disk('public')->delete($lesson->video_thumbnail);
            }

            $lesson->delete();
        });

        return redirect()
            ->route($this->contentRoute($course), $course)
            ->with('success', 'Lesson deleted successfully.');
    }

    public function move(Request $request, Course $course, Lesson $lesson)
    {
        $this->authorizeCourse($course);
        $this->ensureLessonBelongsToCourse($course, $lesson);

        $data = $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        DB::transaction(function () use ($course, $lesson, $data) {
            $lessons = $course->sections()
                ->findOrFail($lesson->section_id)
                ->lessons()
                ->orderBy('order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $index = $lessons->search(
                fn ($item) => $item->id === $lesson->id
            );

            if ($index === false) {
                return;
            }

            $targetIndex = $data['direction'] === 'up'
                ? $index - 1
                : $index + 1;

            if (! $lessons->has($targetIndex)) {
                return;
            }

            $other = $lessons[$targetIndex];
            $currentOrder = $lesson->order;

            $lesson->update(['order' => $other->order]);
            $other->update(['order' => $currentOrder]);
        });

        return redirect()
            ->route($this->contentRoute($course), $course)
            ->with('success', 'Lesson order updated.');
    }

    private function ensureLessonBelongsToCourse(
        Course $course,
        Lesson $lesson
    ): void {
        abort_unless(
            $lesson->section()->where('course_id', $course->id)->exists(),
            404
        );
    }

    private function validateLesson(
        Request $request,
        Course $course,
        ?Lesson $lesson = null
    ): array {
        $data = $request->validate([
            'section_id' => [
                'required',
                Rule::exists('sections', 'id')
                    ->where('course_id', $course->id),
            ],
            'title' => 'required|string|max:200',
            'type' => 'required|in:video,article,quiz',
            // A video lesson uses exactly one source: an uploaded file or a YouTube/Vimeo link.
            'video_source' => 'required_if:type,video|nullable|in:upload,url',
            'video_url' => 'nullable|url|max:2048',
            'video_file' => 'nullable|file|mimes:mp4,webm,mov|max:512000',
            'video_thumbnail_file' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'duration_minutes' => 'nullable|integer|min:0',
            'content' => 'nullable|string',
            'reading_time_minutes' => 'nullable|integer|min:0',
            // Quiz settings, ignored for other lesson types. 
            'quiz_instructions' => 'exclude_unless:type,quiz|nullable|string|max:5000',
            'quiz_time_limit_minutes' => 'exclude_unless:type,quiz|nullable|integer|min:1|max:600',
            // Not sent at all: the default (70%) is kept. Sent empty: an error.
            'quiz_passing_score' => 'exclude_unless:type,quiz|sometimes|required|integer|min:1|max:100',
            'quiz_max_attempts' => 'exclude_unless:type,quiz|nullable|integer|min:1|max:100',
            'quiz_show_review' => 'exclude_unless:type,quiz|sometimes|boolean',
            'is_free_preview' => 'sometimes|boolean',
            'resources' => 'sometimes|array|max:10',
            'resources.*' => 'file|mimes:pdf,zip,doc,docx,ppt,pptx|max:20480',
        ], [
            'video_source.required_if' => 'Choose where the video comes from.',
            'quiz_passing_score.required' => 'Enter the passing score.',
        ]);

        if ($data['type'] === 'video') {
            if ($data['video_source'] === 'url') {
                if (! Lesson::embedUrlFor($data['video_url'] ?? null)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'video_url' => filled($data['video_url'] ?? null)
                            ? 'Only YouTube and Vimeo links are supported.'
                            : 'Paste a YouTube or Vimeo link.',
                    ]);
                }

                // The link is the only source: no uploaded file stays attached.
                $data['video_path'] = null;
            } else {
                // When editing, an already uploaded video counts, unless it is being removed.
                $keepsExistingVideo = $lesson?->video_path
                    && ! $request->boolean('remove_video_file');

                if (! $request->hasFile('video_file') && ! $keepsExistingVideo) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'video_file' => 'Upload a video file.',
                    ]);
                }

                // The uploaded file is the only source: no external link is kept.
                $data['video_url'] = null;
            }
        }


        unset($data['video_source']);

        if (
            $data['type'] === 'article'
            && ! $request->filled('content')
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'content' => 'Article content is required.',
            ]);
        }


        if ($data['type'] === 'quiz') {
            // The quiz block has its own instructions field, so it does not
            // clash with the (hidden) article content field.
            $data['content'] = $data['quiz_instructions'] ?? null;
            $data['quiz_show_review'] = $request->boolean('quiz_show_review');
        }

        unset(
            $data['quiz_instructions'],
            $data['video_file'],
            $data['video_thumbnail_file'],
            $data['resources']
        );

        $data['is_free_preview'] = $request->boolean('is_free_preview');

        return $data;
    }

    private function validateRemovals(Request $request, Lesson $lesson): array
    {
        return $request->validate([
            'remove_video_file' => 'sometimes|boolean',
            'remove_video_thumbnail' => 'sometimes|boolean',
            'remove_resources' => 'sometimes|array',
            'remove_resources.*' => [
                'integer',
                // Only resources that belong to this lesson can be removed.
                Rule::exists('lesson_resources', 'id')
                    ->where('lesson_id', $lesson->id),
            ],
        ]);
    }

    /**
     * Clears the removed files from the database and returns their paths,
     * so they can be deleted from disk after the transaction commits.
     * A newly uploaded video or thumbnail replaces the old one instead (see saveUploads).
     */
    private function applyRemovals(Request $request, Lesson $lesson, array $removals): array
    {
        $paths = [];
        $changes = [];

        if (
            ! empty($removals['remove_video_file'])
            && ! $request->hasFile('video_file')
            && $lesson->video_path
        ) {
            $paths[] = $lesson->video_path;
            $changes['video_path'] = null;
        }

        if (
            ! empty($removals['remove_video_thumbnail'])
            && ! $request->hasFile('video_thumbnail_file')
            && $lesson->video_thumbnail
        ) {
            $paths[] = $lesson->video_thumbnail;
            $changes['video_thumbnail'] = null;
        }

        if ($changes) {
            $lesson->update($changes);
        }

        $resources = $lesson->resources()
            ->whereIn('id', $removals['remove_resources'] ?? [])
            ->get();

        foreach ($resources as $resource) {
            $paths[] = $resource->file_path;
            $resource->delete();
        }

        return $paths;
    }

    private function saveUploads(Request $request, Lesson $lesson): void
    {
        // A file picked before switching to "YouTube or Vimeo link" is ignored.
        if ($request->hasFile('video_file') && $request->input('video_source') !== 'url') {
            $oldPath = $lesson->video_path;
            $path = $request->file('video_file')
                ->store('lessons/videos', 'public');

            $lesson->update(['video_path' => $path]);

            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        if ($request->hasFile('video_thumbnail_file')) {
            $oldPath = $lesson->video_thumbnail;
            $path = $request->file('video_thumbnail_file')
                ->store('lessons/thumbnails', 'public');

            $lesson->update(['video_thumbnail' => $path]);

            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        foreach ($request->file('resources', []) as $file) {
            $path = $file->store('lessons/resources', 'public');

            $lesson->resources()->create([
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
            ]);
        }
    }
}
