
@extends('layouts.main')

@section('title', $instructor->name)

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Instructor</span>
                <h1 class="page-title">{{ $instructor->name }}</h1>
            </div>

            <a href="{{ route('courses.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>
                Browse Courses
            </a>
        </div>

        <div class="row g-4">

            <div class="col-lg-4">
                <div class="panel text-center h-100">

                    <x-ui.avatar :user="$instructor" size="xl" class="mx-auto mb-3" />

                    <h2 class="h4 mb-1">{{ $instructor->name }}</h2>

                    <span class="role-badge role-instructor">Instructor</span>

                    <ul class="list-unstyled profile-facts text-start mt-4 mb-0">

                        <li>
                            <i class="bi bi-collection-play"></i>
                            <div>
                                <span class="fact-label">Published courses</span>
                                <span class="fact-value">{{ $courses->count() }}</span>
                            </div>
                        </li>

                        <li>
                            <i class="bi bi-calendar-check"></i>
                            <div>
                                <span class="fact-label">Member since</span>
                                <span class="fact-value">{{ $instructor->created_at?->format('F Y') }}</span>
                            </div>
                        </li>

                    </ul>
                </div>
            </div>

            <div class="col-lg-8">

                <div class="panel mb-4">
                    <h2 class="panel-title">About</h2>

                    @if (filled($instructor->bio))
                        <p class="mb-0">{!! nl2br(e($instructor->bio)) !!}</p>
                    @else
                        <p class="text-body-secondary mb-0">No bio yet.</p>
                    @endif
                </div>

                <div class="section-heading">
                    <h2 class="h4 mb-0">Courses by {{ $instructor->name }}</h2>
                </div>

                @if ($courses->isEmpty())

                    <x-ui.empty-state icon="bi-easel" title="No published courses yet" />

                @else

                    <div class="row row-cols-1 row-cols-sm-2 g-4">
                        @foreach ($courses as $course)
                            <div class="col">
                                @include('partials.courses.card', ['course' => $course])
                            </div>
                        @endforeach
                    </div>

                @endif

            </div>

        </div>
    </div>
</section>
@endsection
