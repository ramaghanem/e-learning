<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">{{ ucfirst($area) }}</span>
                <h1 class="page-title">Course Details</h1>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route($area . '.courses.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Courses
                </a>

                <a href="{{ route($area . '.courses.content', $course) }}" class="btn btn-primary">
                    <i class="bi bi-collection me-1"></i>
                    Manage Content
                </a>
            </div>
        </div>

        <div class="row g-4">

            <div class="col-lg-4">
                <div class="panel">

                    <div class="mb-3">
                        @include('partials.courses.thumbnail', ['course' => $course, 'class' => 'rounded'])
                    </div>

                    @include('partials.courses.facts', ['course' => $course, 'showStatus' => true])

                </div>
            </div>

            <div class="col-lg-8">

                <div class="panel mb-4">
                    <h2 class="h4 mb-2">{{ $course->title }}</h2>

                    <p class="text-body-secondary mb-0">
                        {{ $course->short_description }}
                    </p>
                </div>

                @include('partials.courses.about', ['course' => $course])

                @include('partials.courses.curriculum', ['course' => $course])

            </div>

        </div>
    </div>
</section>
