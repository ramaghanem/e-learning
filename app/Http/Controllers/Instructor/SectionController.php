<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SectionController extends Controller
{
    private function authorizeCourse(Course $course): void
    {
        abort_unless(
            (int) $course->instructor_id === (int) auth()->id(),
            403
        );
    }

    public function store(Request $request, Course $course)
    {
        $this->authorizeCourse($course);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
        ]);

        $data['order'] = ((int) $course->sections()->max('order')) + 1;

        $course->sections()->create($data);

        return redirect()
            ->route('instructor.courses.content', $course)
            ->with('success', 'Section added successfully.');
    }

    public function edit(Course $course, Section $section)
{
    $this->authorizeCourse($course);

    abort_unless($section->course_id === $course->id, 404);

    return view('instructor.courses.sections.edit', compact(
        'course',
        'section'
    ));
}

    public function update(Request $request, Course $course, Section $section)
    {
        $this->authorizeCourse($course);
        abort_unless($section->course_id === $course->id, 404);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
        ]);

        $section->update($data);

        return redirect()
            ->route('instructor.courses.content', $course)
            ->with('success', 'Section updated successfully.');
    }

    public function destroy(Course $course, Section $section)
    {
        $this->authorizeCourse($course);
        abort_unless($section->course_id === $course->id, 404);

        if ($section->lessons()->exists()) {
            return redirect()
                ->route('instructor.courses.content', $course)
                ->with('error', 'Cannot delete a section that contains lessons.');
        }

        $section->delete();

        return redirect()
            ->route('instructor.courses.content', $course)
            ->with('success', 'Section deleted successfully.');
    }

    public function move(Request $request, Course $course, Section $section)
    {
        $this->authorizeCourse($course);
        abort_unless($section->course_id === $course->id, 404);

        $data = $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        DB::transaction(function () use ($course, $section, $data) {
            $sections = $course->sections()
                ->orderBy('order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $index = $sections->search(
                fn ($item) => $item->id === $section->id
            );

            if ($index === false) {
                return;
            }

            $targetIndex = $data['direction'] === 'up'
                ? $index - 1
                : $index + 1;

            if (! $sections->has($targetIndex)) {
                return;
            }

            $other = $sections[$targetIndex];
            $currentOrder = $section->order;

            $section->update(['order' => $other->order]);
            $other->update(['order' => $currentOrder]);
        });

        return redirect()
            ->route('instructor.courses.content', $course)
            ->with('success', 'Section order updated.');
    }
}
