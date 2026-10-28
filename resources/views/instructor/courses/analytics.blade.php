@extends('layouts.main')

@section('title', 'Analytics · ' . $course->title)

@php
    $filters = [
        'all' => 'All',
        'in-progress' => 'In Progress',
        'completed' => 'Completed',
        'inactive' => 'Inactive',
    ];

    $completionRate = $course->completionRate();
    $averageProgress = $course->averageProgress();
@endphp

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Course analytics</span>
                <h1 class="page-title">{{ $course->title }}</h1>

                <div class="mt-2">
                    @include('partials.courses.status-badge', ['course' => $course])
                </div>
            </div>

            <a href="{{ route('instructor.courses.content', $course) }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Manage
            </a>
        </div>

        <div class="row g-3 mb-4">

            <div class="col-sm-6 col-lg-3">
                <x-ui.stat-card
                    icon="bi-people"
                    label="Total enrollments"
                    :value="$course->enrollments_count"
                />
            </div>

            <div class="col-sm-6 col-lg-3">
                <x-ui.stat-card
                    icon="bi-activity"
                    label="Active students"
                    :value="$course->active_students_count"
                    hint="Opened the course in the last {{ \App\Models\Course::ACTIVE_DAYS }} days"
                    tone="blue"
                />
            </div>

            <div class="col-sm-6 col-lg-3">
                <x-ui.stat-card
                    icon="bi-award"
                    label="Completion rate"
                    :value="$completionRate === null ? '—' : $completionRate . '%'"
                    :hint="$completionRate === null ? 'No students yet' : $course->completed_students_count . ' of ' . $course->enrollments_count . ' completed'"
                    tone="amber"
                />
            </div>

            <div class="col-sm-6 col-lg-3">
                <x-ui.stat-card
                    icon="bi-graph-up"
                    label="Average progress"
                    :value="$averageProgress === null ? '—' : $averageProgress . '%'"
                    :hint="$averageProgress === null ? 'No students yet' : 'Across all enrolled students'"
                    tone="rose"
                />
            </div>

        </div>

        <div class="panel p-0 overflow-hidden">

            <div class="panel-header">
                <h2 class="panel-title mb-0">Students</h2>
            </div>

            @if ($course->enrollments_count === 0)

                <x-ui.empty-state
                    icon="bi-people"
                    title="No students enrolled yet"
                    class="border-0 rounded-0"
                >
                    Students who enroll in this course will appear here.
                </x-ui.empty-state>

            @else

                <div class="px-4 pt-4">

                    <form method="GET" action="{{ route('instructor.courses.analytics', $course) }}" class="mb-3">
                        @if ($status !== 'all')
                            <input type="hidden" name="status" value="{{ $status }}">
                        @endif

                        <div class="input-group">
                            <input
                                type="search"
                                name="search"
                                value="{{ $search }}"
                                class="form-control"
                                placeholder="Search by student name or email"
                                aria-label="Search students"
                            >

                            <button type="submit" class="btn btn-primary" aria-label="Search">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>

                    <ul class="nav nav-pills course-tabs gap-2 mb-3">
                        @foreach ($filters as $key => $label)
                            <li class="nav-item">
                                <a
                                    href="{{ route('instructor.courses.analytics', array_filter([
                                        'course' => $course,
                                        'status' => $key === 'all' ? null : $key,
                                        'search' => $search !== '' ? $search : null,
                                    ])) }}"
                                    @class(['nav-link', 'active' => $status === $key])
                                    @if ($status === $key) aria-current="page" @endif
                                >
                                    {{ $label }}
                                    <span class="badge rounded-pill ms-1">{{ $counts[$key] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                </div>

                @if ($enrollments->isEmpty())

                    <x-ui.empty-state
                        icon="bi-search"
                        title="No students found"
                        class="border-0 rounded-0"
                    >
                        No students match this filter{{ $search !== '' ? ' and search' : '' }}.

                        <x-slot:action>
                            <a href="{{ route('instructor.courses.analytics', $course) }}" class="btn btn-outline-primary">
                                Show All Students
                            </a>
                        </x-slot:action>
                    </x-ui.empty-state>

                @else

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0 data-table">

                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Enrolled</th>
                                    <th style="min-width: 12rem;">Progress</th>
                                    <th class="text-end">Last accessed</th>
                                </tr>
                            </thead>

                            <tbody>

                                @foreach ($enrollments as $enrollment)

                                    <tr>

                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <x-ui.avatar :user="$enrollment->student" size="sm" />

                                                <div class="min-w-0">
                                                    <div class="fw-semibold text-truncate">{{ $enrollment->student->name }}</div>
                                                    <div class="small text-body-secondary text-truncate">{{ $enrollment->student->email }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="small text-body-secondary text-nowrap">
                                            {{ $enrollment->enrolled_at?->format('M j, Y') }}
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div
                                                    class="progress flex-grow-1"
                                                    role="progressbar"
                                                    aria-label="Progress of {{ $enrollment->student->name }}"
                                                    aria-valuenow="{{ $enrollment->progress }}"
                                                    aria-valuemin="0"
                                                    aria-valuemax="100"
                                                    style="height: 8px;"
                                                >
                                                    <div class="progress-bar" style="width: {{ $enrollment->progress }}%"></div>
                                                </div>

                                                <span class="small fw-semibold">{{ $enrollment->progress }}%</span>
                                            </div>
                                        </td>

                                        <td class="text-end small text-body-secondary text-nowrap">
                                            {{ $enrollment->last_accessed_at?->diffForHumans() ?? 'Never' }}
                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                    @if ($enrollments->hasPages())
                        <div class="px-4 py-3 border-top">
                            {{ $enrollments->links('pagination::bootstrap-5') }}
                        </div>
                    @endif

                @endif

            @endif

        </div>

    </div>
</section>
@endsection
