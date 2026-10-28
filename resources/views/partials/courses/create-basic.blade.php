{{--
    Create Course, page 1 of 2: basic information.
    Shared by the Admin and Instructor pages.

    Expects:
        $area         'admin' or 'instructor'
        $categories   categories for the dropdown
        $course       optional. Pass the values already entered (for example an
                      unsaved Course filled from the session) so they are shown
                      again when the user comes back from page 2.

    The two pages are NOT connected yet: the form action is "#".
    Each button submits this same form, and the backend decides what to do:
        Save as Draft        sends status=draft
        Publish              sends status=published
        Next: Course Details sends no status. Keep the values and show page 2.
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
                    <h2 class="panel-title">Basic information</h2>

                    <p class="text-body-secondary">
                        Step 1 of 2
                    </p>

                    <form method="POST" action="{{ route($area . '.courses.store') }}" enctype="multipart/form-data" novalidate>

                        @csrf

                       @include('partials.courses.fields-basic', [
    'area' => $area,
    'categories' => $categories,
    'instructors' => $instructors ?? collect(),
    'course' => $course ?? null,
])

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">
                                <i class="bi bi-file-earmark me-1"></i>
                                Save as Draft
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

                            <button type="submit" name="action" value="next"
                                class="btn btn-outline-primary ms-auto">
                                Next: Course Details
                                <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
</section>
