{{--
    Preview Course, shared by the Admin and Instructor pages.
    Shows the course the way students will see it, including drafts.

    Expects:
        $area     'admin' or 'instructor'
        $course   the course, with category, instructor and sections.lessons.resources
        $lesson   optional. When given, that lesson is shown with the course navigation.
--}}
@php
    $lesson = $lesson ?? null;
    $lessonLink = fn ($item) => route($area . '.courses.preview', [$course, $item]);
    $overviewUrl = route($area . '.courses.preview', $course);

    // Every lesson of the course, in order, to work out Previous / Next.
    $lessons = $course->sections->flatMap->lessons->values();
@endphp

<section class="page-section">
    <div class="container">

        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 mb-4" role="status">
            <i class="bi bi-eye-fill"></i>

            <div class="me-auto">
                <strong>Preview Mode</strong>
                <span class="d-none d-sm-inline">— this is how students will see this course.</span>
                @include('partials.courses.status-badge', ['course' => $course])
            </div>

            <a href="{{ route($area . '.courses.content', $course) }}" class="btn btn-sm btn-outline-dark">
                <i class="bi bi-x-lg me-1"></i>
                Exit Preview
            </a>
        </div>

        @if (! $lesson)

            {{-- Course page, as on the student course page --}}
            <div class="page-heading">
                <div>
                    <span class="eyebrow">{{ $course->category?->name ?? 'Course' }}</span>
                    <h1 class="page-title">{{ $course->title }}</h1>
                </div>
            </div>

            <div class="row g-4">

                <div class="col-lg-8">

                    <div class="panel mb-4">
                        <p class="mb-0">
                            {{ $course->short_description }}
                        </p>
                    </div>

                    @include('partials.courses.about', ['course' => $course])

                    @include('partials.courses.curriculum', [
                        'course' => $course,
                        'lessonLink' => $lessonLink,
                    ])
                </div>

                <div class="col-lg-4">
                    <div class="panel">

                        <div class="mb-3">
                            @include('partials.courses.thumbnail', [
                                'course' => $course,
                                'class' => 'rounded',
                            ])
                        </div>

                        <button type="button" class="btn btn-primary w-100 mb-1" disabled>
                            Enroll Now
                        </button>

                        <p class="small text-body-secondary text-center mb-3">
                            Enrolling is disabled in preview.
                        </p>

                        @if ($lessons->isNotEmpty())
                            <a href="{{ $lessonLink($lessons->first()) }}" class="btn btn-outline-primary w-100 mb-3">
                                <i class="bi bi-play-circle me-1"></i>
                                Start First Lesson
                            </a>
                        @endif

                        @include('partials.courses.facts', ['course' => $course])

                    </div>
                </div>

            </div>

        @else

            {{-- One lesson, with the course navigation --}}
            @include('partials.courses.lesson-view', [
                'course' => $course,
                'lesson' => $lesson,
                'navigableLessons' => $lessons,
                'lessonLink' => $lessonLink,
                'overviewUrl' => $overviewUrl,
            ])

        @endif

    </div>
</section>
