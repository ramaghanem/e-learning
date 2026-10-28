{{--
    The text parts of a course: full description, learning objectives,
    requirements and target audience.

    Usage:
        @include('partials.courses.about', ['course' => $course])
        @include('partials.courses.about', ['course' => $course, 'parts' => ['objectives']])

    parts: which panels to show, in this order (default: all four, in the order below).

    Assumes "learning_objectives" is an array on the course.
--}}
@php
    $parts = $parts ?? ['description', 'objectives', 'requirements', 'audience'];
@endphp

@foreach ($parts as $part)

    @if ($part === 'description')
        <div class="panel mb-4">
            <h2 class="panel-title">Description</h2>

            <p class="mb-0">{!! nl2br(e($course->description)) !!}</p>
        </div>
    @endif

    @if ($part === 'objectives')
        <div class="panel mb-4">
            <h2 class="panel-title">What You'll Learn</h2>

            @if (filled($course->learning_objectives))
                <ul class="list-unstyled mb-0">
                    @foreach ($course->learning_objectives as $objective)
                        <li class="d-flex gap-2 mb-2">
                            <i class="bi bi-check2-circle text-accent"></i>
                            <span>{{ $objective }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-body-secondary mb-0">
                    No learning objectives added yet.
                </p>
            @endif
        </div>
    @endif

    @if ($part === 'requirements')
        <div class="panel mb-4">
            <h2 class="panel-title">Requirements</h2>

            @if (filled($course->requirements))
                <p class="mb-0">{!! nl2br(e($course->requirements)) !!}</p>
            @else
                <p class="text-body-secondary mb-0">
                    No requirements added yet.
                </p>
            @endif
        </div>
    @endif

    @if ($part === 'audience')
        <div class="panel mb-4">
            <h2 class="panel-title">Who This Course Is For</h2>

            @if (filled($course->target_audience))
                <p class="mb-0">{!! nl2br(e($course->target_audience)) !!}</p>
            @else
                <p class="text-body-secondary mb-0">
                    No target audience added yet.
                </p>
            @endif
        </div>
    @endif

@endforeach
