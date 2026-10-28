@extends('layouts.main')

@section('title', 'My Statistics')

@php
    // 750 -> "12h 30m", 45 -> "45m", 0 -> "0m"
    $learningTime = $learningMinutes >= 60
        ? intdiv($learningMinutes, 60) . 'h' . ($learningMinutes % 60 ? ' ' . ($learningMinutes % 60) . 'm' : '')
        : $learningMinutes . 'm';

    $typeIcon = fn ($lesson) => match ($lesson->type) {
        'article' => 'bi-file-earmark-text',
        'quiz' => 'bi-patch-question',
        default => 'bi-play-circle',
    };
@endphp

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Student</span>
                <h1 class="page-title">My Statistics</h1>
            </div>

            <a href="{{ route('student.courses.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-journal-bookmark me-1"></i>
                My Courses
            </a>
        </div>

        {{-- Total learning time, with a short summary of the courses (the full cards are on the dashboard). --}}
        <div class="panel mb-4">

            <div class="d-flex align-items-center gap-3">
                <span class="stat-icon stat-teal"><i class="bi bi-clock-history"></i></span>

                <div>
                    <div class="stat-label">Total learning time</div>
                    <div class="stat-value fs-2" id="learningTime">{{ $learningTime }}</div>
                </div>
            </div>

            {{-- Each number opens the matching My Courses tab. --}}
            <div class="d-flex flex-wrap align-items-center gap-2 small text-body-secondary mt-3 pt-3 border-top">
                <a href="{{ route('student.courses.index') }}" class="text-reset">
                    <strong class="text-body">{{ $summary['enrolled'] }}</strong> enrolled
                </a>
                <span aria-hidden="true">·</span>
                <a href="{{ route('student.courses.index', ['tab' => 'in-progress']) }}" class="text-reset">
                    <strong class="text-body">{{ $summary['in_progress'] }}</strong> in progress
                </a>
                <span aria-hidden="true">·</span>
                <a href="{{ route('student.courses.index', ['tab' => 'completed']) }}" class="text-reset">
                    <strong class="text-body">{{ $summary['completed'] }}</strong> completed
                </a>
                <span aria-hidden="true">·</span>
                <a href="{{ route('student.courses.index', ['tab' => 'completed']) }}" class="text-reset">
                    <strong class="text-body">{{ $summary['certificates'] }}</strong>
                    {{ Str::plural('certificate', $summary['certificates']) }}
                </a>
            </div>

        </div>

        <div class="panel p-0 overflow-hidden">

            <div class="panel-header">
                <h2 class="panel-title mb-0">Recent Activity</h2>
                <span class="small text-body-secondary">Last 10 completed lessons</span>
            </div>

            @if ($recentLessons->isEmpty())

                <x-ui.empty-state
                    icon="bi-check2-square"
                    title="No completed lessons yet"
                    class="border-0 rounded-0"
                >
                    Lessons you mark as complete will appear here.

                    <x-slot:action>
                        <a href="{{ route('student.courses.index') }}" class="btn btn-primary">
                            Go to My Courses
                        </a>
                    </x-slot:action>
                </x-ui.empty-state>

            @else

                <ul class="list-group list-group-flush">

                    @foreach ($recentLessons as $lesson)

                        <li class="list-group-item list-group-item-action px-4 py-3 d-flex align-items-center gap-3 position-relative">

                            <i class="bi bi-check-circle-fill text-success fs-5" aria-hidden="true"></i>

                            <div class="flex-grow-1 min-w-0">
                                <a
                                    href="{{ route('courses.learn', [$lesson->section->course, $lesson]) }}"
                                    class="stretched-link fw-semibold text-reset text-decoration-none d-block text-truncate"
                                >
                                    {{ $lesson->title }}
                                </a>

                                <div class="small text-body-secondary text-truncate">
                                    <i class="bi {{ $typeIcon($lesson) }} me-1" aria-hidden="true"></i>
                                    {{ $lesson->section->course->title }}
                                    @if ($lesson->durationMinutes())
                                        · {{ $lesson->durationMinutes() }} min
                                    @endif
                                </div>
                            </div>

                            <span
                                class="small text-body-secondary text-nowrap"
                                title="{{ $lesson->pivot->created_at?->format('M j, Y g:i A') }}"
                            >
                                {{ $lesson->pivot->created_at?->diffForHumans() }}
                            </span>

                        </li>

                    @endforeach

                </ul>

            @endif

        </div>

    </div>
</section>
@endsection
