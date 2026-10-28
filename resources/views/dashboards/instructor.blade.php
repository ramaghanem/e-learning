@extends('layouts.main')

@section('title', 'Instructor Dashboard')

@section('content')
<section class="page-section">
    <div class="container">

        <x-ui.dashboard-header
            eyebrow="Instructor dashboard"
            title="Welcome back, {{ auth()->user()->name }}!"
            subtitle="Here's how your courses are doing."
        >
            <x-slot:action>
                <a href="{{ route('instructor.courses.create') }}" class="btn btn-light">
                    <i class="bi bi-plus-lg me-1"></i>
                    Create New Course
                </a>
            </x-slot:action>
        </x-ui.dashboard-header>

        <div class="row g-3 mb-5">

            <div class="col-sm-6 col-lg-4">
                <x-ui.stat-card
                    icon="bi-collection-play"
                    label="Courses created"
                    :value="$coursesCount"
                />
            </div>

            <div class="col-sm-6 col-lg-4">
                <x-ui.stat-card
                    icon="bi-people"
                    label="Students enrolled"
                    :value="$studentsCount"
                    tone="blue"
                />
            </div>

            <div class="col-sm-6 col-lg-4">
                <x-ui.stat-card
                    icon="bi-star-fill"
                    label="Average course rating"
                    tone="amber"
                    :value="$averageRating ? number_format($averageRating, 1) . ' / 5' : '—'"
                    :hint="$averageRating ? null : 'No reviews yet'"
                />
            </div>

        </div>

        <div class="section-heading">
            <h2 class="h4 mb-0">My Courses</h2>
        </div>

        @if ($courses->isEmpty())

            <x-ui.empty-state
                icon="bi-easel"
                title="You haven't created any courses yet"
            >
                Create your first course, then add sections and lessons to it.

                <x-slot:action>
                    <a href="{{ route('instructor.courses.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>
                        Create New Course
                    </a>
                </x-slot:action>
            </x-ui.empty-state>

        @else

            <div class="panel p-0 overflow-hidden">

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0 data-table">

                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Status</th>
                                <th class="text-end">Enrollments</th>
                                <th class="text-end">Active (7 days)</th>
                                <th class="text-end">Completion rate</th>
                                <th class="text-end">Avg. progress</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach ($courses as $course)

                                <tr>

                                    <td class="fw-semibold">
                                        {{ $course->title }}
                                    </td>

                                    <td>
                                        @if ($course->status === 'published')
                                            <span class="badge text-bg-success">
                                                Published
                                            </span>
                                        @else
                                            <span class="badge text-bg-secondary">
                                                Draft
                                            </span>
                                        @endif
                                    </td>

                                    <td class="text-end">
                                        {{ $course->enrollments_count ?? 0 }}
                                    </td>

                                    <td class="text-end">
                                        {{ $course->active_students_count ?? 0 }}
                                    </td>

                                    <td class="text-end">
                                        {{ $course->completionRate() === null ? '—' : $course->completionRate() . '%' }}
                                    </td>

                                    <td class="text-end">
                                        {{ $course->averageProgress() === null ? '—' : $course->averageProgress() . '%' }}
                                    </td>

                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('instructor.courses.analytics', $course) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-bar-chart me-1"></i>
                                            Analytics
                                        </a>

                                        <a href="{{ route('instructor.courses.content', $course) }}" class="btn btn-sm btn-outline-primary">
                                            Manage
                                        </a>
                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

        @endif

    </div>
</section>
@endsection
