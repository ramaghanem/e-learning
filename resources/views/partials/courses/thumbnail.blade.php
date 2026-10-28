
@if ($course?->thumbnail)
    <img
        src="{{ asset('storage/' . $course->thumbnail) }}"
        alt=""
        class="course-card-thumb {{ $class ?? '' }}"
    >
@else
    <div class="course-card-thumb course-card-thumb-empty {{ $class ?? '' }}">
        <i class="bi bi-play-btn"></i>
    </div>
@endif
