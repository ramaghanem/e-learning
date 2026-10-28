@extends('layouts.main')

@section('title', 'User Details')

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Admin</span>
                <h1 class="page-title">User Details</h1>
            </div>

            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Users List
            </a>
        </div>

        <div class="row g-4">

            <div class="col-lg-4">
                <div class="panel text-center h-100">

                    <x-ui.avatar :user="$user" size="xl" class="mx-auto mb-3" />

                    <h2 class="h4 mb-1">{{ $user->name }}</h2>

                    <span class="role-badge role-{{ $user->role }}">
                        {{ ucfirst($user->role) }}
                    </span>

                    <ul class="list-unstyled profile-facts text-start mt-4 mb-0">

                        <li>
                            <i class="bi bi-envelope"></i>
                            <div>
                                <span class="fact-label">Email</span>
                                <span class="fact-value">{{ $user->email }}</span>
                            </div>
                        </li>

                        <li>
                            <i class="bi bi-telephone"></i>
                            <div>
                                <span class="fact-label">Phone</span>
                                <span class="fact-value">
                                    {{ $user->phone ?? 'Not added yet' }}
                                </span>
                            </div>
                        </li>

                        <li>
                            <i class="bi bi-calendar-check"></i>
                            <div>
                                <span class="fact-label">Registration date</span>
                                <span class="fact-value">
                                    {{ $user->created_at?->format('F j, Y') }}
                                </span>
                            </div>
                        </li>

                        <li>
                            <i class="bi bi-clock-history"></i>
                            <div>
                                <span class="fact-label">Last login</span>
                                <span class="fact-value">
                                    {{ $user->last_login_at?->format('F j, Y') ?? 'Never' }}
                                </span>
                            </div>
                        </li>

                        <li>
                            <i class="bi bi-shield-check"></i>
                            <div>
                                <span class="fact-label">Account status</span>
                                <span class="fact-value">
                                    @if ($user->status === 'active')
                                        <span class="badge text-bg-success">
                                            Active
                                        </span>
                                    @else
                                        <span class="badge text-bg-secondary">
                                            Inactive
                                        </span>
                                    @endif
                                </span>
                            </div>
                        </li>

                    </ul>
                </div>
            </div>

            <div class="col-lg-8">

                <div class="panel mb-4">
                    <h2 class="panel-title">About</h2>

                    @if (filled($user->bio))
                        <p class="mb-0">{!! nl2br(e($user->bio)) !!}</p>
                    @else
                        <p class="text-body-secondary mb-0">
                            No bio yet.
                        </p>
                    @endif
                </div>

                @if ($user->role === 'instructor')

                    <div class="row g-3 mb-4">

                        <div class="col-sm-6">
                            <x-ui.stat-card
                                icon="bi-collection-play"
                                label="Courses created"
                                :value="$stats['courses']"
                            />
                        </div>

                        <div class="col-sm-6">
                            <x-ui.stat-card
                                icon="bi-people"
                                label="Total students"
                                :value="$stats['students']"
                                tone="blue"
                            />
                        </div>

                    </div>

                    <div class="panel p-0 overflow-hidden">

                        <div class="panel-header">
                            <h2 class="panel-title mb-0">
                                Courses Created
                            </h2>
                        </div>

                        @if ($courses->isEmpty())

                            <x-ui.empty-state
                                icon="bi-easel"
                                title="No courses created yet"
                                class="border-0 rounded-0"
                            />

                        @else

                            <div class="table-responsive">

                                <table class="table table-hover align-middle mb-0 data-table">

                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Status</th>
                                            <th class="text-end">Enrollments</th>
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

                                            </tr>

                                        @endforeach

                                    </tbody>

                                </table>

                            </div>

                        @endif

                    </div>

                @elseif ($user->role === 'student')

                    <div class="panel p-0 overflow-hidden">

                        <div class="panel-header">
                            <h2 class="panel-title mb-0">
                                Enrolled Courses
                            </h2>
                        </div>

                        @if ($courses->isEmpty())

                            <x-ui.empty-state
                                icon="bi-journal-x"
                                title="No enrolled courses yet"
                                class="border-0 rounded-0"
                            />

                        @else

                            <div class="table-responsive">

                                <table class="table table-hover align-middle mb-0 data-table">

                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Progress</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        @foreach ($courses as $course)

                                            <tr>

                                                <td class="fw-semibold">
                                                    {{ $course->title }}
                                                </td>

                                                <td>
                                                    <div class="d-flex align-items-center gap-2">

                                                        <div
                                                            class="progress flex-grow-1"
                                                            role="progressbar"
                                                            aria-valuenow="{{ $course->progress ?? 0 }}"
                                                            aria-valuemin="0"
                                                            aria-valuemax="100"
                                                            style="height: 8px;"
                                                        >
                                                            <div
                                                                class="progress-bar"
                                                                style="width: {{ $course->progress ?? 0 }}%"
                                                            ></div>
                                                        </div>

                                                        <span class="small fw-semibold">
                                                            {{ $course->progress ?? 0 }}%
                                                        </span>

                                                    </div>
                                                </td>

                                            </tr>

                                        @endforeach

                                    </tbody>

                                </table>

                            </div>

                        @endif

                    </div>

                @endif

            </div>

        </div>
    </div>
</section>
@endsection
