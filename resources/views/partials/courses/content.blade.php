@php
    $sections = $course->sections ?? collect();
@endphp

<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Course content</span>
                <h1 class="page-title">{{ $course->title }}</h1>

                <div class="mt-2">
                    @include('partials.courses.status-badge', ['course' => $course])
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route($area . '.courses.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Courses
                </a>

                <a href="{{ route($area . '.courses.preview', $course) }}" class="btn btn-outline-primary">
                    <i class="bi bi-eye me-1"></i>
                    Preview Course
                </a>

                {{-- Course analytics are for the instructor only. --}}
                @if ($area === 'instructor')
                    <a href="{{ route('instructor.courses.analytics', $course) }}" class="btn btn-outline-primary">
                        <i class="bi bi-bar-chart me-1"></i>
                        Analytics
                    </a>
                @endif

                <button type="button" class="btn btn-primary" data-bs-toggle="collapse"
                    data-bs-target="#addSectionForm">
                    <i class="bi bi-plus-lg me-1"></i>
                    Add Section
                </button>
            </div>
        </div>
        <div class="collapse mb-4" id="addSectionForm">
            <div class="panel">
                <h2 class="panel-title">Add New Section</h2>

                <form method="POST" action="{{ route($area . '.courses.sections.store', $course) }}">
                    @csrf

                    <div class="mb-3">
                        <label for="section-title" class="form-label">
                            Section Title
                        </label>

                        <input type="text" id="section-title" name="title" class="form-control" maxlength="200"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="section-description" class="form-label">
                            Description (Optional)
                        </label>

                        <textarea id="section-description" name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Save Section
                    </button>
                </form>
            </div>
        </div>
        @if ($sections->isEmpty())

            <x-ui.empty-state icon="bi-collection" title="No sections yet" class="mb-4">
                Add your first section, then add lessons to it.
            </x-ui.empty-state>
        @else
            @foreach ($sections as $section)
                <div class="panel p-0 overflow-hidden mb-4">

                    <div class="panel-header align-items-center">
                        <h2 class="panel-title mb-0">
                            Section {{ $loop->iteration }}: {{ $section->title }}
                        </h2>

                        <div class="text-nowrap">

                            <a href="{{ route($area . '.courses.lessons.create', $course) }}"
                                class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-lg me-1"></i>
                                Add Lesson
                            </a>

                            <a href="{{ route($area . '.courses.sections.edit', [$course, $section]) }}"
                                class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>

                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                data-bs-target="#deleteSectionModal-{{ $section->id }}">
                                Delete
                            </button>

                        </div>
                    </div>

                    @if ($section->lessons->isEmpty())
                        <x-ui.empty-state icon="bi-play-circle" title="No lessons in this section yet"
                            class="border-0 rounded-0" />
                    @else
                        <ul class="list-group list-group-flush">

                            @foreach ($section->lessons as $lesson)
                                <li
                                    class="list-group-item px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">

                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <i class="bi bi-play-circle text-accent"></i>
                                        <span class="fw-semibold">{{ $lesson->title }}</span>
                                    </div>

                                    <div class="text-nowrap">

                                        @if ($lesson->type === 'quiz')
                                            <a href="{{ route($area . '.courses.lessons.questions.index', [$course, $lesson]) }}"
                                                class="btn btn-sm btn-primary">
                                                <i class="bi bi-patch-question me-1"></i>
                                                Questions
                                            </a>
                                        @endif

                                        <a href="{{ route($area . '.courses.lessons.edit', [$course, $lesson]) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>

                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteLessonModal-{{ $lesson->id }}">
                                            Delete
                                        </button>

                                    </div>

                                </li>
                            @endforeach

                        </ul>
                    @endif

                </div>
            @endforeach

            @foreach ($sections as $section)
                @include('partials.courses.delete-modal', [
                    'id' => 'deleteSectionModal-' . $section->id,
                    'title' => 'Delete Section',
                    'action' => route($area . '.courses.sections.destroy', [$course, $section]),
                ])

                @foreach ($section->lessons as $lesson)
                    @include('partials.courses.delete-modal', [
                        'id' => 'deleteLessonModal-' . $lesson->id,
                        'title' => 'Delete Lesson',
                        'action' => route($area . '.courses.lessons.destroy', [$course, $lesson]),
                    ])
                @endforeach
            @endforeach

        @endif

        @if ($course->status !== 'published')
            <div class="panel">
                <h2 class="panel-title">Publish course</h2>

                <p class="text-body-secondary">
                    This course is a draft, so students cannot see it yet.
                    A course needs at least one lesson before it can be published.
                </p>

                <form method="POST" action="{{ auth()->user()->role === 'admin'
          ? route('admin.courses.publish', $course)
          : route('instructor.courses.publish', $course) }}" class="m-0">

                    @csrf
                    @method('PATCH')

                    <input type="hidden" name="status" value="published">

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-send me-1"></i>
                        Publish
                    </button>

                </form>
            </div>
        @endif

    </div>
</section>
