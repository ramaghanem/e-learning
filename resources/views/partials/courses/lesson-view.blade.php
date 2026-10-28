@php
    $canOpenLesson = $canOpenLesson ?? null;

    $index = $navigableLessons->search(fn ($item) => $item->id === $lesson->id);
    $previous = $index !== false && $index > 0 ? $navigableLessons[$index - 1] : null;
    $next = $index !== false ? ($navigableLessons[$index + 1] ?? null) : null;

    $sectionNumber = $course->sections->search(fn ($section) => $section->id === $lesson->section_id) + 1;
    $section = $course->sections->firstWhere('id', $lesson->section_id);
@endphp

<div class="page-heading">
    <div>
        <span class="eyebrow">Section {{ $sectionNumber }}: {{ $section?->title }}</span>
        <h1 class="page-title">{{ $lesson->title }}</h1>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-2 small text-body-secondary">
            <span class="badge text-bg-light">{{ ucfirst($lesson->type) }}</span>

            @if ($lesson->type === 'video' && $lesson->duration_minutes)
                <span><i class="bi bi-clock me-1"></i>{{ $lesson->duration_minutes }} min</span>
            @elseif ($lesson->type === 'article' && $lesson->reading_time_minutes)
                <span><i class="bi bi-clock me-1"></i>{{ $lesson->reading_time_minutes }} min read</span>
            @endif

            @if ($lesson->is_free_preview)
                <span class="badge text-bg-success">Free preview</span>
            @endif
        </div>
    </div>

    <a href="{{ $overviewUrl }}" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left me-1"></i>
        Course Overview
    </a>
</div>

<div class="row g-4">

    <div class="col-lg-8">

        @include('partials.courses.lesson-content', ['lesson' => $lesson])

        <div class="d-flex flex-wrap justify-content-between gap-2">
            @if ($previous)
                <a href="{{ $lessonLink($previous) }}" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Previous Lesson
                </a>
            @else
                <span></span>
            @endif

            @if ($next)
                <a href="{{ $lessonLink($next) }}" class="btn btn-primary">
                    Next Lesson
                    <i class="bi bi-arrow-right ms-1"></i>
                </a>
            @else
                <a href="{{ $overviewUrl }}" class="btn btn-outline-primary">
                    Back to Course Overview
                </a>
            @endif
        </div>

    </div>

    <div class="col-lg-4">
        @include('partials.courses.curriculum', [
            'course' => $course,
            'lessonLink' => $lessonLink,
            'currentLesson' => $lesson,
            'canOpenLesson' => $canOpenLesson,
        ])
    </div>

</div>
