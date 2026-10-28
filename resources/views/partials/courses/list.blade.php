
{{--
    Courses list, shared by the Admin and Instructor pages.

    Expects:
        $area      'admin' or 'instructor'
        $courses   Admin: all courses. Instructor: only their own courses.
--}}

<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">{{ ucfirst($area) }}</span>
                <h1 class="page-title">Courses</h1>
            </div>

            <a
                href="{{ route($area . '.courses.create') }}"
                class="btn btn-primary"
            >
                <i class="bi bi-plus-lg me-1"></i>
                Create Course
            </a>
        </div>

        <div class="panel mb-4">

            <form
                method="GET"
                action="{{ url()->current() }}"
                class="row g-3 align-items-end"
            >

                <div class="col-md-6">
                    <label for="search" class="form-label">
                        Search
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control"
                        placeholder="Search by course title"
                    >
                </div>

                <div class="col-md-4">
                    <label for="status" class="form-label">
                        Status
                    </label>

                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>

                        <option
                            value="draft"
                            @selected(request('status') === 'draft')
                        >
                            Draft
                        </option>

                        <option
                            value="published"
                            @selected(request('status') === 'published')
                        >
                            Published
                        </option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>
                        Search
                    </button>
                </div>

            </form>

        </div>

        @if ($courses->isEmpty())

            <x-ui.empty-state
                icon="bi-collection-play"
                title="No courses found"
            />

        @else

            <div class="panel p-0 overflow-hidden">

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0 data-table">

                        <thead>
                            <tr>
                                <th>Course title</th>
                                <th>Category</th>
                                <th>Instructor</th>
                                <th>Difficulty level</th>
                                <th class="text-end">Price</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach ($courses as $course)

                                <tr>

                                    <td class="fw-semibold">
                                        {{ $course->title }}
                                    </td>

                                    <td>
                                        {{ $course->category?->name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $course->instructor?->name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ ucfirst($course->difficulty) }}
                                    </td>

                                    <td class="text-end text-nowrap">
                                        {{ $course->price > 0
                                            ? '$' . number_format($course->price, 2)
                                            : 'Free' }}
                                    </td>

                                    <td>
                                        @include(
                                            'partials.courses.status-badge',
                                            ['course' => $course]
                                        )
                                    </td>

                                    <td class="text-end text-nowrap">

                                        @if ($course->status === 'draft')
                                            <a
                                                href="{{ route($area . '.courses.details', $course) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Continue Details
                                            </a>
                                        @endif

                                        <a
                                            href="{{ route($area . '.courses.show', $course) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            View
                                        </a>

                                        <a
                                            href="{{ route($area . '.courses.edit', $course) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Edit
                                        </a>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteCourseModal-{{ $course->id }}"
                                        >
                                            Delete
                                        </button>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

            @foreach ($courses as $course)

                @include('partials.courses.delete-modal', [
                    'id' => 'deleteCourseModal-' . $course->id,
                    'title' => 'Delete Course',
                    'action' => route($area . '.courses.destroy', $course),
                ])

            @endforeach

            @if ($courses instanceof \Illuminate\Contracts\Pagination\Paginator)
                <div class="mt-4">
                    {{ $courses->links('pagination::bootstrap-5') }}
                </div>
            @endif

        @endif

    </div>
</section>
