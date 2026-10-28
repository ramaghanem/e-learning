<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">{{ ucfirst($area) }}</span>
                <h1 class="page-title">Edit Lesson</h1>
                <p class="text-body-secondary">
                    {{ $course->title }}
                </p>
            </div>

            <a href="{{ route($area . '.courses.content', $course) }}"
               class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Course Content
            </a>
        </div>

        <div class="row g-4">

            <div class="col-lg-9">

                <div class="panel">
                    <h2 class="panel-title">Lesson information</h2>

                    <form method="POST"
                          action="{{ route($area . '.courses.lessons.update', [$course, $lesson]) }}"
                          enctype="multipart/form-data"
                          novalidate>

                        @csrf
                        @method('PATCH')

                        @include('partials.courses.lessons.fields')

                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i>
                            Save Changes
                        </button>

                    </form>
                </div>

            </div>
        </div>

    </div>
</section>
