@extends('layouts.main')

@section('title', 'Student Dashboard')

@section('content')
<section class="page-section">
    <div class="container">

        <x-ui.dashboard-header
            eyebrow="Student dashboard"
            title="Welcome back, {{ auth()->user()->name }}!"
            subtitle="Pick up where you left off or find something new to learn."
        >
            <x-slot:action>
                <a href="{{ url('/courses') }}" class="btn btn-light">
                    <i class="bi bi-search me-1"></i>
                    Browse Courses
                </a>

                <a href="{{ route('student.statistics') }}" class="btn btn-outline-light">
                    <i class="bi bi-bar-chart me-1"></i>
                    My Statistics
                </a>
            </x-slot:action>
        </x-ui.dashboard-header>

        <div class="row g-3 mb-5">

            <div class="col-sm-6 col-lg-3">
                <x-ui.stat-card
                    icon="bi-journal-bookmark"
                    label="Enrolled courses"
                    :value="$enrolledCount"
                />
            </div>

            <div class="col-sm-6 col-lg-3">
                <x-ui.stat-card
                    icon="bi-hourglass-split"
                    label="In progress"
                    :value="$inProgressCount"
                    tone="rose"
                />
            </div>

            <div class="col-sm-6 col-lg-3">
                <x-ui.stat-card
                    icon="bi-check2-circle"
                    label="Completed courses"
                    :value="$completedCount"
                    tone="blue"
                />
            </div>

            <div class="col-sm-6 col-lg-3">
                <x-ui.stat-card
                    icon="bi-award"
                    label="Certificates earned"
                    :value="$certificatesCount"
                    tone="amber"
                />
            </div>

        </div>

        <div class="section-heading d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h4 mb-0">Continue Learning</h2>

            @if ($enrolledCount > 0)
                <a href="{{ route('student.courses.index') }}" class="btn btn-sm btn-outline-primary">
                    View My Courses
                </a>
            @endif
        </div>

        @if ($continueLearning->isEmpty())

            <x-ui.empty-state
                :icon="$enrolledCount > 0 ? 'bi-award' : 'bi-journal-x'"
                title="No courses in progress"
            >
                @if ($enrolledCount > 0)
                    You have completed all your courses. Well done!
                @else
                    You haven't enrolled in any courses yet.
                @endif

                <x-slot:action>
                    <a href="{{ url('/courses') }}" class="btn btn-primary">
                        Browse Courses
                    </a>
                </x-slot:action>
            </x-ui.empty-state>

        @else

            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">

                @foreach ($continueLearning as $enrollment)
                    <div class="col">
                        @include('partials.courses.enrolled-card', [
                            'enrollment' => $enrollment,
                            'completedLessons' => $lessonStats[$enrollment->course_id]['completed'] ?? 0,
                            'totalLessons' => $lessonStats[$enrollment->course_id]['total'] ?? 0,
                        ])
                    </div>
                @endforeach

            </div>

        @endif

    </div>
</section>
@endsection
