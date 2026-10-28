{{--
    The Enroll Now / Go to Course button on the student course page.

    Usage:
        @include('partials.courses.enroll-box', ['course' => $course, 'isEnrolled' => $isEnrolled])

    isEnrolled   true when the logged-in user is enrolled in the course (CourseController@show).

    Enroll Now posts to courses.enroll (EnrollmentController@store):
        guest        -> login page with "Please login to enroll", then back to this course
        student      -> enrolled right away (free and paid), then the Course Learning page
--}}
@php
    $isEnrolled = $isEnrolled ?? false;
@endphp

@if ($isEnrolled)

    <a href="{{ route('courses.learn', $course) }}" class="btn btn-primary btn-lg w-100 mb-4">
        <i class="bi bi-play-circle me-1"></i>
        Go to Course
    </a>

@elseif (auth()->check() && auth()->user()->role !== 'student')

    {{-- Admins and instructors cannot enroll. --}}
    <button type="button" class="btn btn-primary btn-lg w-100" disabled>
        Enroll Now
    </button>

    <p class="small text-body-secondary text-center mt-2 mb-4">
        Only students can enroll in courses.
    </p>

@else

    <form method="POST" action="{{ route('courses.enroll', $course) }}" class="mb-4">
        @csrf

        <button type="submit" class="btn btn-primary btn-lg w-100">
            Enroll Now
        </button>

        @guest
            <p class="small text-body-secondary text-center mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Please login to enroll.
            </p>
        @endguest
    </form>

@endif
