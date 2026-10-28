<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">{{ ucfirst($area) }}</span>
                <h1 class="page-title">Edit Question</h1>
                <p class="text-body-secondary">
                    {{ $lesson->title }} · {{ $course->title }}
                </p>
            </div>

            <a href="{{ route($area . '.courses.lessons.questions.index', [$course, $lesson]) }}"
               class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Questions
            </a>
        </div>

        <div class="row g-4">

            <div class="col-lg-9">

                <div class="panel">
                    <h2 class="panel-title">Question</h2>

                    <form method="POST"
                          action="{{ route($area . '.courses.lessons.questions.update', [$course, $lesson, $question]) }}"
                          novalidate>

                        @csrf
                        @method('PATCH')

                        @include('partials.courses.questions.fields')

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
