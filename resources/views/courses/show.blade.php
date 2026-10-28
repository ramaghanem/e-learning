@extends('layouts.main')

@section('title', $course->title)

@php
    $instructor = $course->instructor;
    $lessons = $course->sections->flatMap->lessons;

    
    $isEnrolled = $isEnrolled ?? false;
    $instructorUrl = $instructor
        ? route('instructors.show', $instructor)
        : null;

    $hours = (int) ($course->duration_hours ?? 0);
    $minutes = (int) ($course->duration_minutes ?? 0);
    $duration = trim(($hours ? $hours . 'h ' : '') . ($minutes ? $minutes . 'm' : ''));


    $videoMinutes = (int) $lessons->where('type', 'video')->sum('duration_minutes');
    $resourceCount = $lessons->sum(fn ($lesson) => $lesson->resources->count());

    $videoLength = $videoMinutes >= 60
        ? intdiv($videoMinutes, 60) . 'h' . ($videoMinutes % 60 ? ' ' . ($videoMinutes % 60) . 'm' : '')
        : $videoMinutes . ' min';
@endphp

@section('content')
    <section class="page-section">
        <div class="container">

            <div class="page-heading">
                <div>
                    <span class="eyebrow">{{ $course->category?->name ?? 'Course' }}</span>
                    <h1 class="page-title">{{ $course->title }}</h1>
                </div>

                <a href="{{ route('courses.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Courses
                </a>
            </div>

            <div class="row g-4">

                <div class="col-lg-8">

                    {{-- Header: short description and key details --}}
                    <div class="panel mb-4">
                        <p class="fs-5 mb-3">
                            {{ $course->short_description }}
                        </p>

                        <div class="d-flex flex-wrap align-items-center gap-3 small text-body-secondary">
                            @if ($instructor)
                                <span class="d-inline-flex align-items-center gap-2 position-relative">
                                    <x-ui.avatar :user="$instructor" size="sm" />
                                    <span>
                                        Created by
                                        @if ($instructorUrl)
                                            <a href="{{ $instructorUrl }}" class="fw-bold stretched-link">{{ $instructor->name }}</a>
                                        @else
                                            <strong class="text-body">{{ $instructor->name }}</strong>
                                        @endif
                                    </span>
                                </span>
                            @endif

                            {{-- The real number of enrollments: loadCount('enrollments') in CourseController@show. --}}
                            @isset($course->enrollments_count)
                                <span>
                                    <i class="bi bi-people me-1"></i>
                                    {{ $course->enrollments_count }} {{ Str::plural('student', $course->enrollments_count) }}
                                </span>
                            @endisset

                            @if ($course->updated_at)
                                <span><i class="bi bi-calendar3 me-1"></i>Last updated {{ $course->updated_at->format('F Y') }}</span>
                            @endif

                            @if ($course->language)
                                <span><i class="bi bi-translate me-1"></i>{{ $course->language }}</span>
                            @endif

                            @if ($duration)
                                <span><i class="bi bi-clock me-1"></i>{{ $duration }} total</span>
                            @endif

                            <span><i class="bi bi-bar-chart me-1"></i>{{ ucfirst($course->difficulty) }}</span>
                        </div>
                    </div>

                    @include('partials.courses.about', ['course' => $course, 'parts' => ['objectives']])

                    @include('partials.courses.course-content', ['course' => $course, 'isEnrolled' => $isEnrolled])

                    @include('partials.courses.about', [
                        'course' => $course,
                        'parts' => ['requirements', 'description', 'audience'],
                    ])

                    @if ($instructor)
                        @php
                            $publishedCourses = $instructor->courses()->where('status', 'published')->count();
                        @endphp

                        <div class="panel mb-4">
                            <h2 class="panel-title">Instructor</h2>

                            <div class="d-flex flex-column flex-sm-row gap-3 mt-3">
                                @if ($instructorUrl)
                                    <a href="{{ $instructorUrl }}" aria-hidden="true" tabindex="-1">
                                        <x-ui.avatar :user="$instructor" size="lg" />
                                    </a>
                                @else
                                    <x-ui.avatar :user="$instructor" size="lg" />
                                @endif

                                <div>
                                    <div class="fw-bold fs-5">
                                        @if ($instructorUrl)
                                            <a href="{{ $instructorUrl }}" class="text-reset">{{ $instructor->name }}</a>
                                        @else
                                            {{ $instructor->name }}
                                        @endif
                                    </div>

                                    <div class="small text-body-secondary mb-2">
                                        <i class="bi bi-collection-play me-1"></i>
                                        {{ $publishedCourses }} {{ Str::plural('published course', $publishedCourses) }}
                                    </div>

                                    @if (filled($instructor->bio))
                                        <p class="mb-0">{!! nl2br(e($instructor->bio)) !!}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                </div>

                <div class="col-lg-4">
                    <div class="panel sticky-panel">

                        <div class="mb-3">
                            @include('partials.courses.thumbnail', [
                                'course' => $course,
                                'class' => 'rounded',
                            ])
                        </div>

                        <div class="price-tag mb-3">
                            @if ($course->price > 0)
                                ${{ number_format($course->price, 2) }}
                            @else
                                Free
                            @endif
                        </div>

                        @include('partials.courses.enroll-box', ['course' => $course, 'isEnrolled' => $isEnrolled])

                        <h2 class="filter-heading">This course includes</h2>

                        <ul class="list-unstyled small mb-0">
                            @if ($videoMinutes > 0)
                                <li class="mb-2">
                                    <i class="bi bi-camera-video text-accent me-2"></i>
                                    {{ $videoLength }} of video
                                </li>
                            @endif

                            @if ($resourceCount > 0)
                                <li class="mb-2">
                                    <i class="bi bi-download text-accent me-2"></i>
                                    {{ $resourceCount }} downloadable {{ Str::plural('resource', $resourceCount) }}
                                </li>
                            @endif

                            <li class="mb-2">
                                <i class="bi bi-award text-accent me-2"></i>
                                Certificate of completion
                            </li>

                            <li class="mb-2">
                                <i class="bi bi-infinity text-accent me-2"></i>
                                Lifetime access
                            </li>
                        </ul>

                    </div>
                </div>

            </div>
        </div>
    </section>
@endsection
