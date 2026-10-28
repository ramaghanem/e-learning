@php
    $course = $enrollment->course;
    $progress = (int) $enrollment->progress;
    $isCompleted = $enrollment->isCompleted();
@endphp

<div class="course-card h-100">

    @include('partials.courses.thumbnail', ['course' => $course])

    <div class="p-3 d-flex flex-column flex-grow-1">

        <div class="d-flex flex-wrap gap-1 mb-2">
            @if ($course->category)
                <span class="tag-badge">{{ $course->category->name }}</span>
            @endif

            @if ($isCompleted)
                <span class="badge text-bg-success">Completed</span>
            @endif
        </div>

        <h3 class="course-card-title">
            {{ $course->title }}
        </h3>

        @if ($course->instructor)
            <div class="d-flex align-items-center gap-2 small text-body-secondary mb-3">
                <x-ui.avatar :user="$course->instructor" size="sm" />
                <span class="text-truncate">{{ $course->instructor->name }}</span>
            </div>
        @endif

        <div class="d-flex justify-content-between small fw-semibold mb-1">
            <span>{{ $completedLessons }} of {{ $totalLessons }} {{ Str::plural('lesson', $totalLessons) }}</span>
            <span>{{ $progress }}%</span>
        </div>

        <div
            class="progress mb-2"
            role="progressbar"
            aria-label="Progress in {{ $course->title }}"
            aria-valuenow="{{ $progress }}"
            aria-valuemin="0"
            aria-valuemax="100"
            style="height: 8px;"
        >
            <div class="progress-bar" style="width: {{ $progress }}%"></div>
        </div>

        <p class="small text-body-secondary mb-3">
            <i class="bi bi-clock-history me-1"></i>
            @if ($enrollment->last_accessed_at)
                Last accessed {{ $enrollment->last_accessed_at->diffForHumans() }}
            @else
                Not started yet
            @endif
        </p>

        <div class="d-grid gap-2 mt-auto">
            <a href="{{ route('courses.learn', $course) }}" class="btn btn-outline-primary btn-sm">
                {{ $isCompleted ? 'Review Course' : 'Continue Learning' }}
            </a>

            @if ($isCompleted)
                <a href="{{ route('courses.certificate', $course) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-download me-1"></i>
                    Download Certificate
                </a>
            @endif
        </div>

    </div>

</div>
