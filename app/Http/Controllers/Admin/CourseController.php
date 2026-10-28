<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::with(['category', 'instructor'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.courses.index', compact('courses'));
    }

public function create()
{
    $categories = Category::orderBy('name')->get();

    $instructors = User::where('role', 'instructor')
        ->orderBy('name')
        ->get(['id', 'name', 'email']);

    return view('admin.courses.create', compact(
        'categories',
        'instructors'
    ));
}


public function store(Request $request)
{
    $rules = [
        'title' => 'required|string|max:200|unique:courses,title',
        'short_description' => 'required|string|max:200',
        'description' => 'required|string',
        'category_id' => 'required|exists:categories,id',
        'difficulty' => 'required|in:beginner,intermediate,advanced',
        'language' => 'required|string|max:100',
        'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'instructor_id' => [
            'required',
            Rule::exists('users', 'id')
                ->where(fn ($query) => $query->where('role', 'instructor')),
        ],
        'action' => 'required|in:draft,next',
    ];

    $data = $request->validate($rules);

    $action = $data['action'];
    unset($data['action']);

    $data['status'] = 'draft';
    $data['instructor_id'] = (int) $data['instructor_id'];

    if ($request->hasFile('thumbnail')) {
        $data['thumbnail'] = $request->file('thumbnail')
            ->store('courses', 'public');
    }

    $course = Course::create($data);

    if ($action === 'next') {
        return redirect()
            ->route('admin.courses.details', $course)
            ->with('success', 'Basic information saved. Complete the course details.');
    }

    return redirect()
        ->route('admin.courses.content', $course)
        ->with('success', 'Course saved as draft.');
}


    public function edit(Course $course)
{
    $categories = Category::orderBy('name')->get();

    $instructors = User::where('role', 'instructor')
        ->orderBy('name')
        ->get(['id', 'name', 'email']);

    return view('admin.courses.edit', compact(
        'course',
        'categories',
        'instructors'
    ));
}


public function update(Request $request, Course $course)
{
    $data = $request->validate([
        'title' => [
            'required',
            'string',
            'max:200',
            Rule::unique('courses', 'title')->ignore($course->id),
        ],
        'short_description' => 'required|string|max:200',
        'description' => 'required|string',
        'category_id' => 'required|exists:categories,id',
        'instructor_id' => [
            'required',
            Rule::exists('users', 'id')
                ->where(fn ($query) => $query->where('role', 'instructor')),
        ],
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
        'status' => 'required|in:draft,published',
    ]);

    if ($request->hasFile('thumbnail')) {
        $data['thumbnail'] = $request->file('thumbnail')
            ->store('courses', 'public');
    } else {
        unset($data['thumbnail']);
    }

    $data['learning_objectives'] = array_values(array_filter(
        $data['learning_objectives'] ?? [],
        fn ($objective) => filled($objective)
    ));

    // Keep the status selected by the admin.
    $course->update($data);

    return redirect()
        ->route('admin.courses.index')
        ->with('success', 'Course updated successfully.');
}


    public function show(Course $course)
{
    $course->load([
        'category',
        'instructor',
        'sections.lessons',
    ]);

    return view('admin.courses.show', compact('course'));
}

    public function destroy(Course $course)
    {
        if ($course->thumbnail) {
            \Illuminate\Support\Facades\Storage::disk('public')
                ->delete($course->thumbnail);
        }

        $course->delete();

        return redirect()
            ->route('admin.courses.index')
            ->with('success', 'Course deleted successfully.');
    }



public function createDetails(Course $course)
{
    $categories = Category::orderBy('name')->get();

    $instructors = User::where('role', 'instructor')
        ->orderBy('name')
        ->get(['id', 'name', 'email']);

    return view('admin.courses.create-details', compact(
        'course',
        'categories',
        'instructors'
    ));
}

public function storeDetails(Request $request, Course $course)
{
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
        ->route('admin.courses.content', $course)
        ->with('success', 'Course details saved. Add lessons before publishing.');
}

public function content(Course $course)
{

    $course->load([
        'sections.lessons.resources',
    ]);

    return view('admin.courses.content', compact('course'));
}

public function preview(Course $course, ?Lesson $lesson = null)
{
    $course->load([
        'category',
        'instructor',
        'sections.lessons.resources',
    ]);

  
    abort_if(
        $lesson && ! $course->sections->flatMap->lessons->contains('id', $lesson->id),
        404
    );

    return view('admin.courses.preview', compact('course', 'lesson'));
}



public function publish(Course $course)
{
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

