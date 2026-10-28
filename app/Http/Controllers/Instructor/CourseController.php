<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::with(['category', 'instructor'])
            ->where('instructor_id', auth()->id())
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('instructor.courses.index', compact('courses'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();

        return view('instructor.courses.create', compact('categories'));
    }


public function store(Request $request)
{
    $data = $request->validate([
        'title' => 'required|string|max:200|unique:courses,title',
        'short_description' => 'required|string|max:200',
        'description' => 'required|string',
        'category_id' => 'required|exists:categories,id',
        'difficulty' => 'required|in:beginner,intermediate,advanced',
        'language' => 'required|string|max:100',
        'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'action' => 'required|in:draft,next',
    ]);

    $action = $data['action'];
    unset($data['action']);

    $data['instructor_id'] = auth()->id();
    $data['status'] = 'draft';

    if ($request->hasFile('thumbnail')) {
        $data['thumbnail'] = $request->file('thumbnail')
            ->store('courses', 'public');
    }

    $course = Course::create($data);

    if ($action === 'next') {
        return redirect()
            ->route('instructor.courses.details', $course)
            ->with('success', 'Basic information saved. Complete the course details.');
    }

    return redirect()
        ->route('instructor.courses.content', $course)
        ->with('success', 'Course saved as draft.');
}

public function createDetails(Course $course)
{
    $this->authorizeCourse($course);

    return view('instructor.courses.create-details', compact('course'));
}

public function storeDetails(Request $request, Course $course)
{
    $this->authorizeCourse($course);

    $data = $request->validate([
        'learning_objectives' => 'nullable|array',
        'learning_objectives.*' => 'nullable|string|max:255',
        'requirements' => 'nullable|string',
        'target_audience' => 'nullable|string',
        'duration_hours' => 'nullable|integer|min:0',
        'duration_minutes' => 'nullable|integer|between:0,59',
        'price' => 'nullable|numeric|min:0',
    ]);

    $data['learning_objectives'] = array_values(array_filter(
        $data['learning_objectives'] ?? [],
        fn ($objective) => filled($objective)
    ));

    $course->update($data);

    return redirect()
        ->route('instructor.courses.content', $course)
        ->with('success', 'Course details saved. Add lessons before publishing.');
}

public function content(Course $course)
{
    $this->authorizeCourse($course);
    $course->load([
        'sections.lessons.resources',
    ]);
    return view('instructor.courses.content', compact('course'));
}

/**
 * Course analytics: the 4 numbers and the list of enrolled students.
 * Filters: ?status=in-progress|completed|inactive, ?search=name or email.
 */
public function analytics(Request $request, Course $course)
{
    $this->authorizeCourse($course);

    // Bring every student's progress up to date before counting.
    Enrollment::syncProgressForCourses([$course->id]);

    $course->loadCount(Course::analyticsCounts())
        ->loadAvg('enrollments', 'progress');

    $status = in_array($request->input('status'), ['in-progress', 'completed', 'inactive'], true)
        ? $request->input('status')
        : 'all';

    $search = trim((string) $request->input('search'));
    $activeSince = now()->subDays(Course::ACTIVE_DAYS);

    $enrollments = $course->enrollments()
        ->with('student')
        ->when($search !== '', function ($query) use ($search) {
            $query->whereHas('student', function ($studentQuery) use ($search) {
                $studentQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        })
        ->when($status === 'in-progress', fn ($query) => $query->where('progress', '<', 100))
        ->when($status === 'completed', fn ($query) => $query->where('progress', 100))
        ->when($status === 'inactive', function ($query) use ($activeSince) {
            // Not opened in the last 7 days, or never opened.
            $query->where(function ($inactiveQuery) use ($activeSince) {
                $inactiveQuery->whereNull('last_accessed_at')
                    ->orWhere('last_accessed_at', '<', $activeSince);
            });
        })
        ->latest('enrolled_at')
        ->paginate(15)
        ->withQueryString();

    // Number of students in each filter (without the search).
    $counts = [
        'all' => $course->enrollments_count,
        'in-progress' => $course->enrollments_count - $course->completed_students_count,
        'completed' => $course->completed_students_count,
        'inactive' => $course->enrollments_count - $course->active_students_count,
    ];

    return view('instructor.courses.analytics', compact('course', 'enrollments', 'status', 'search', 'counts'));
}

public function preview(Course $course, ?Lesson $lesson = null)
{
    $this->authorizeCourse($course);

    $course->load([
        'category',
        'instructor',
        'sections.lessons.resources',
    ]);

    // The lesson must belong to this course.
    abort_if(
        $lesson && ! $course->sections->flatMap->lessons->contains('id', $lesson->id),
        404
    );

    return view('instructor.courses.preview', compact('course', 'lesson'));
}


    public function edit(Course $course)
    {
        $this->authorizeCourse($course);

        $categories = Category::orderBy('name')->get();

        return view('instructor.courses.edit', compact('course', 'categories'));
    }

    public function update(Request $request, Course $course)
    {
        $this->authorizeCourse($course);

        $data = $request->validate([
            'title' => [
                'required', 'string', 'max:200',
                Rule::unique('courses', 'title')->ignore($course->id),
            ],
            'short_description' => 'required|string|max:200',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'difficulty' => 'required|in:beginner,intermediate,advanced',
            'language' => 'required|string|max:100',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'learning_objectives' => 'nullable|array',
            'learning_objectives.*' => 'nullable|string|max:255',
            'requirements' => 'nullable|string',
            'target_audience' => 'nullable|string',
            'duration_hours' => 'nullable|integer|min:0',
            'duration_minutes' => 'nullable|integer|between:0,59',
            'price' => 'nullable|numeric|min:0',
        ]);

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail) {
                Storage::disk('public')->delete($course->thumbnail);
            }

            $data['thumbnail'] = $request->file('thumbnail')
                ->store('courses', 'public');
        } else {
            unset($data['thumbnail']);
        }

        $data['learning_objectives'] = array_values(array_filter(
            $data['learning_objectives'] ?? [],
            fn ($objective) => filled($objective)
        ));

        $course->update($data);

        return redirect()
            ->route('instructor.courses.index')
            ->with('success', 'Course updated successfully.');
    }

    public function show(Course $course)
{
    $this->authorizeCourse($course);

    $course->load([
        'category',
        'instructor',
        // 'sections.lessons',
    ]);

    return view('instructor.courses.show', compact('course'));
}



    public function destroy(Course $course)
    {
        $this->authorizeCourse($course);

        if ($course->thumbnail) {
            Storage::disk('public')->delete($course->thumbnail);
        }

        $course->delete();

        return redirect()
            ->route('instructor.courses.index')
            ->with('success', 'Course deleted successfully.');
    }

    private function authorizeCourse(Course $course): void
    {
        abort_unless(
            $course->instructor_id === auth()->id(),
            403
        );
    }


    public function publish(Course $course)
{
    $this->authorizeCourse($course);

    if (! $course->sections()->whereHas('lessons')->exists()) {
        return back()->with(
            'error',
            'Add at least one lesson before publishing this course.'
        );
    }

    $course->update([
        'status' => 'published',
    ]);

    return back()->with('success', 'Course published successfully.');
}
}
