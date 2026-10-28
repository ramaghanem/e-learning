{{--
    Create Course, page 2 of 2: course details.
    Shared by the Admin and Instructor pages.

    Expects:
        $area     'admin' or 'instructor'
        $course   optional. Pass the values already entered so they are shown again.

    The two pages are NOT connected yet: the form action and the Back link are "#".
    This form only sends the page 2 fields. The page 1 values (including the
    uploaded thumbnail) have to be kept by the backend, for example in the session
    or as a saved draft, until this form is submitted.

    Save as Draft sends status=draft and Publish sends status=published.
--}}
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">{{ ucfirst($area) }}</span>
                <h1 class="page-title">Create New Course</h1>
            </div>

            <a href="{{ route($area . '.courses.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Courses
            </a>
        </div>

        <div class="row g-4">

            <div class="col-lg-9">

                <div class="panel">
                    <h2 class="panel-title">Course details</h2>

                    <p class="text-body-secondary">
                        Step 2 of 2
                    </p>

                    <form method="POST"
                          action="{{ route($area . '.courses.details.store', $course) }}"
                          novalidate>

                        @csrf

                        @include('partials.courses.fields-details')

                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <a href="{{ route($area . '.courses.edit', $course) }}" class="btn btn-outline-primary me-auto">
                                <i class="bi bi-arrow-left me-1"></i>
                                Back
                            </a>

                            <button
                                type="submit"
                                name="status"
                                value="draft"
                                class="btn btn-outline-secondary"
                            >
                                <i class="bi bi-file-earmark me-1"></i>
                                 Save & Continue to Lessons
                            </button>

                            {{-- <button
                                type="submit"
                                name="status"
                                value="published"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-send me-1"></i>
                                Publish
                            </button> --}}
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
</section>
