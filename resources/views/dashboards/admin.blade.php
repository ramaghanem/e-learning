@extends('layouts.main')

@section('title', 'Admin Dashboard')

@section('content')
<section class="page-section">
    <div class="container">

        <x-ui.dashboard-header
            eyebrow="Admin dashboard"
            title="Welcome, {{ auth()->user()->name }}!"
            subtitle="An overview of what's happening on LearnHub."
        />

        <div class="row g-3 mb-4">

            <div class="col-sm-6 col-lg-4">
                <x-ui.stat-card
                    icon="bi-people"
                    label="Total users"
                    :value="$usersCount"
                />
            </div>

            <div class="col-sm-6 col-lg-4">
                <x-ui.stat-card
                    icon="bi-collection-play"
                    label="Total courses"
                    :value="$coursesCount"
                    tone="blue"
                />
            </div>

            <div class="col-sm-6 col-lg-4">
                <x-ui.stat-card
                    icon="bi-journal-check"
                    label="Total enrollments"
                    :value="$enrollmentsCount"
                    tone="amber"
                />
            </div>

        </div>

        <div class="row g-3 mb-5">

            <div class="col-md-4">
                <a href="{{ url('/admin/users') }}" class="quick-link">
                    <i class="bi bi-person-gear"></i>
                    <span>Manage Users</span>
                    <i class="bi bi-arrow-right ms-auto"></i>
                </a>
            </div>

            <div class="col-md-4">
                <a href="{{ route('admin.courses.index') }}" class="quick-link">
                    <i class="bi bi-collection"></i>
                    <span>Manage Courses</span>
                    <i class="bi bi-arrow-right ms-auto"></i>
                </a>
            </div>

            <div class="col-md-4">
                <a href="{{ route('admin.categories.index') }}" class="quick-link">
                    <i class="bi bi-tags"></i>
                    <span>Manage Categories</span>
                    <i class="bi bi-arrow-right ms-auto"></i>
                </a>
            </div>

        </div>

        <div class="row g-4">

            <div class="col-xl-6">

                <div class="panel p-0 overflow-hidden h-100">

                    <div class="panel-header">
                        <h2 class="panel-title mb-0">
                            Recent Registrations
                        </h2>

                        <span class="small text-body-secondary">
                            Last 10 users
                        </span>
                    </div>

                    @if ($recentUsers->isEmpty())

                        <x-ui.empty-state
                            icon="bi-person-plus"
                            title="No registrations yet"
                            class="border-0 rounded-0"
                        />

                    @else

                        <div class="table-responsive">

                            <table class="table table-hover align-middle mb-0 data-table">

                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th class="text-end">Joined</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    @foreach ($recentUsers as $recentUser)

                                        <tr>

                                            <td>
                                                <div class="d-flex align-items-center gap-2">

                                                    <x-ui.avatar
                                                        :user="$recentUser"
                                                        size="sm"
                                                    />

                                                    <div class="min-w-0">

                                                        <div class="fw-semibold text-truncate">
                                                            {{ $recentUser->name }}
                                                        </div>

                                                        <div class="small text-body-secondary text-truncate">
                                                            {{ $recentUser->email }}
                                                        </div>

                                                    </div>

                                                </div>
                                            </td>

                                            <td>
                                                <span class="role-badge role-{{ $recentUser->role }}">
                                                    {{ ucfirst($recentUser->role) }}
                                                </span>
                                            </td>

                                            <td class="text-end small text-body-secondary text-nowrap">
                                                {{ $recentUser->created_at?->format('M j, Y') }}
                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @endif

                </div>

            </div>

            <div class="col-xl-6">

                <div class="panel p-0 overflow-hidden h-100">

                    <div class="panel-header">
                        <h2 class="panel-title mb-0">
                            Recent Enrollments
                        </h2>

                        <span class="small text-body-secondary">
                            Last 10
                        </span>
                    </div>

                    @if ($recentEnrollments->isEmpty())

                        <x-ui.empty-state
                            icon="bi-journal-plus"
                            title="No enrollments yet"
                            class="border-0 rounded-0"
                        >
                            Enrollments will appear here once students join courses.
                        </x-ui.empty-state>

                    @else

                        <div class="table-responsive">

                            <table class="table table-hover align-middle mb-0 data-table">

                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th class="text-end">Date</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    @foreach ($recentEnrollments as $enrollment)

                                        <tr>

                                            <td class="fw-semibold">
                                                {{ $enrollment->student->name }}
                                            </td>

                                            <td>
                                                {{ $enrollment->course->title }}
                                            </td>

                                            <td class="text-end small text-body-secondary text-nowrap">
                                                {{ $enrollment->enrolled_at?->format('M j, Y') }}
                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @endif

                </div>

            </div>

        </div>

    </div>
</section>
@endsection
