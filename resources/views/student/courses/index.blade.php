@extends('layouts.main')

@section('title', 'My Courses')

@php

    $tabs = [
        'all' => ['label' => 'All Courses', 'empty' => "You haven't enrolled in any courses yet"],
        'in-progress' => ['label' => 'In Progress', 'empty' => 'No courses in progress'],
        'completed' => ['label' => 'Completed', 'empty' => "You haven't completed any courses yet"],
    ];
@endphp

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Student</span>
                <h1 class="page-title">My Courses</h1>
            </div>

            <a href="{{ route('courses.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-search me-1"></i>
                Browse Courses
            </a>
        </div>

        @if ($counts['all'] === 0)

            <x-ui.empty-state
                icon="bi-journal-x"
                title="You haven't enrolled in any courses yet"
            >
                Find a course you like and enroll to start learning.

                <x-slot:action>
                    <a href="{{ route('courses.index') }}" class="btn btn-primary">
                        Browse Courses
                    </a>
                </x-slot:action>
            </x-ui.empty-state>

        @else

            <ul class="nav nav-pills course-tabs gap-2 mb-4">
                @foreach ($tabs as $key => $item)
                    <li class="nav-item">
                        <a
                            href="{{ route('student.courses.index', $key === 'all' ? [] : ['tab' => $key]) }}"
                            @class(['nav-link', 'active' => $tab === $key])
                            @if ($tab === $key) aria-current="page" @endif
                        >
                            {{ $item['label'] }}
                            <span class="badge rounded-pill ms-1">{{ $counts[$key] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($enrollments->isEmpty())

                <x-ui.empty-state
                    :icon="$tab === 'completed' ? 'bi-award' : 'bi-hourglass-split'"
                    :title="$tabs[$tab]['empty']"
                >
                    @if ($tab === 'completed')
                        Finish every lesson of a course to complete it and get your certificate.
                    @else
                        All your courses are completed. Well done!
                    @endif

                    <x-slot:action>
                        <a href="{{ route('student.courses.index') }}" class="btn btn-outline-primary">
                            Show All Courses
                        </a>
                    </x-slot:action>
                </x-ui.empty-state>

            @else

                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-4">

                    @foreach ($enrollments as $enrollment)
                        <div class="col">
                            @include('partials.courses.enrolled-card', [
                                'enrollment' => $enrollment,
                                'completedLessons' => $lessonStats[$enrollment->course_id]['completed'] ?? 0,
                                'totalLessons' => $lessonStats[$enrollment->course_id]['total'] ?? 0,
                            ])
                        </div>
                    @endforeach

                </div>

                <div class="mt-4">
                    {{ $enrollments->links('pagination::bootstrap-5') }}
                </div>

            @endif

        @endif

    </div>
</section>
@endsection
