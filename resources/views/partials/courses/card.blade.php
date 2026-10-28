@php
    $hours = (int) ($course->duration_hours ?? 0);
    $minutes = (int) ($course->duration_minutes ?? 0);
    $duration = trim(($hours ? $hours . 'h ' : '') . ($minutes ? $minutes . 'm' : ''));
    $detailsUrl = route('courses.show', $course);
@endphp

<div class="course-card h-100">

    <a href="{{ $detailsUrl }}" tabindex="-1" aria-hidden="true">
        @include('partials.courses.thumbnail', ['course' => $course])
    </a>

    <div class="p-3 d-flex flex-column flex-grow-1">

        <div class="d-flex flex-wrap gap-1 mb-2">
            @if ($course->category)
                <span class="tag-badge">{{ $course->category->name }}</span>
            @endif

            <span class="tag-badge tag-badge-muted">{{ ucfirst($course->difficulty) }}</span>
        </div>

        <h3 class="course-card-title">
            <a href="{{ $detailsUrl }}" class="text-reset text-decoration-none">
                {{ $course->title }}
            </a>
        </h3>

        @if ($course->instructor)
            <div class="d-flex align-items-center gap-2 small text-body-secondary mb-2">
                <x-ui.avatar :user="$course->instructor" size="sm" />
                <span class="text-truncate">{{ $course->instructor->name }}</span>
            </div>
        @endif

        <p class="small text-body-secondary line-clamp-2 mb-3">
            {{ $course->short_description }}
        </p>

        <div class="d-flex align-items-center justify-content-between gap-2 mt-auto pt-3 border-top">

            <div class="d-flex align-items-center flex-wrap gap-2">
                @if ($course->price > 0)
                    <span class="fw-bold">${{ number_format($course->price, 2) }}</span>
                @else
                    <span class="badge text-bg-success">Free</span>
                @endif

                @if ($duration)
                    <span class="small text-body-secondary text-nowrap">
                        <i class="bi bi-clock me-1"></i>{{ $duration }}
                    </span>
                @endif
            </div>

            <a href="{{ $detailsUrl }}" class="btn btn-outline-primary btn-sm text-nowrap">
                View Details
            </a>

        </div>

    </div>

</div>
