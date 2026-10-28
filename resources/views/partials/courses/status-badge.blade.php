{{--
    Draft / Published badge for a course.

    Usage:
        @include('partials.courses.status-badge', ['course' => $course])
--}}
@if ($course->status === 'published')
    <span class="badge text-bg-success">
        Published
    </span>
@else
    <span class="badge text-bg-secondary">
        Draft
    </span>
@endif
