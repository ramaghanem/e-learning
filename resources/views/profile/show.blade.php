@extends('layouts.main')

@section('title', 'My Profile')

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Account</span>
                <h1 class="page-title">My Profile</h1>
            </div>

            <a href="{{ route('profile.edit') }}" class="btn btn-primary">
                <i class="bi bi-pencil-square me-1"></i>
                Edit Profile
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
                                <span class="fact-label">Member since</span>
                                <span class="fact-value">
                                    {{ $user->created_at?->format('F j, Y') }}
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
                            <a href="{{ route('profile.edit') }}">
                                Add a short bio
                            </a>
                        </p>
                    @endif
                </div>

                @if ($user->role === 'instructor')
                    <div class="row g-3">

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
                {{-- Students: course numbers are on the dashboard and on My Statistics. --}}
                @elseif ($user->role === 'admin')
                    <div class="row g-3">

                        <div class="col-sm-6">
                            <x-ui.stat-card
                                icon="bi-people"
                                label="Total users"
                                :value="$stats['users']"
                            />
                        </div>

                        <div class="col-sm-6">
                            <x-ui.stat-card
                                icon="bi-journal-text"
                                label="Total courses"
                                :value="$stats['courses']"
                                tone="blue"
                            />
                        </div>

                    </div>
                @endif

            </div>

        </div>
    </div>
</section>
@endsection
