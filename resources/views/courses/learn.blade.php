
@extends('layouts.main')

@php
    $isEnrolled = $isEnrolled ?? false;
    $enrollment = $enrollment ?? null;
    $completedLessonIds = $completedLessonIds ?? collect();

    $allLessons = $course->sections->flatMap->lessons->values();
    $canOpen = fn ($item) => $isEnrolled || $item->is_free_preview;
    $openLessons = $allLessons->filter($canOpen)->values();

    $lessonLink = fn ($item) => route('courses.learn', [$course, $item]);
    $overviewUrl = route('courses.show', $course);

    if ($lesson) {

        $position = $allLessons->search(fn ($item) => $item->id === $lesson->id) + 1;
        $index = $openLessons->search(fn ($item) => $item->id === $lesson->id);
        $previous = $index !== false && $index > 0 ? $openLessons[$index - 1] : null;
        $next = $index !== false ? ($openLessons[$index + 1] ?? null) : null;

        $isDone = $completedLessonIds->contains($lesson->id);
    }

    $progress = (int) ($enrollment?->progress ?? 0);
@endphp

@section('title', $lesson ? $lesson->title . ' · ' . $course->title : $course->title)

@section('content')
<section class="page-section">
    <div class="container">

        {{-- Top bar --}}
        <div class="panel d-flex flex-wrap align-items-center gap-3 mb-4 py-3">

            <div class="me-auto min-w-0">
                @if ($lesson)
                    <span class="eyebrow mb-0">Lesson {{ $position }} of {{ $allLessons->count() }}</span>
                @endif

                <a href="{{ $overviewUrl }}" class="d-block fw-bold fs-5 text-reset text-decoration-none text-truncate">
                    {{ $course->title }}
                </a>
            </div>

            @if ($isEnrolled)
                <div style="min-width: 14rem;">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span>Course progress</span>
                        <span data-course-progress-text>{{ $progress }}%</span>
                    </div>

                    <div
                        class="progress"
                        role="progressbar"
                        aria-label="Course progress"
                        aria-valuenow="{{ $progress }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        style="height: 8px;"
                        data-course-progress
                    >
                        <div class="progress-bar" style="width: {{ $progress }}%" data-course-progress-bar></div>
                    </div>
                </div>

                {{-- Hidden until the course is completed (also shown right after the last "Mark as Complete"). --}}
                <a
                    href="{{ route('courses.certificate', $course) }}"
                    @class(['btn btn-primary btn-sm', 'd-none' => ! $enrollment->isCompleted()])
                    data-certificate-link
                >
                    <i class="bi bi-download me-1"></i>
                    Download Certificate
                </a>

                <a href="{{ route('student.courses.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to My Courses
                </a>
            @else
                <a href="{{ $overviewUrl }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>
                    Course Overview
                </a>
            @endif

        </div>

        @unless ($isEnrolled)
            <div class="alert alert-info d-flex flex-wrap align-items-center gap-2 mb-4" role="status">
                <i class="bi bi-unlock-fill"></i>

                <div class="me-auto">
                    <strong>Free preview</strong>
                    <span class="d-none d-sm-inline">— enroll in the course to unlock every lesson.</span>
                </div>

                <a href="{{ $overviewUrl }}" class="btn btn-sm btn-primary">
                    Enroll Now
                </a>
            </div>
        @endunless

        @if ($lesson)

            <div class="row g-4">

                {{-- Left sidebar: course curriculum --}}
                <div class="col-lg-4 col-xl-3 order-2 order-lg-1">
                    @include('partials.courses.learn-sidebar', [
                        'course' => $course,
                        'currentLesson' => $lesson,
                        'completedLessonIds' => $completedLessonIds,
                        'canOpenLesson' => $canOpen,
                        'lessonLink' => $lessonLink,
                    ])
                </div>

                {{-- Main: the current lesson --}}
                <div class="col-lg-8 col-xl-9 order-1 order-lg-2">

                    <div class="mb-3">
                        <div class="d-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-2">
                            <span class="badge text-bg-light">{{ ucfirst($lesson->type) }}</span>

                            @if ($lesson->type === 'video' && $lesson->duration_minutes)
                                <span><i class="bi bi-clock me-1"></i>{{ $lesson->duration_minutes }} min</span>
                            @elseif ($lesson->type === 'article' && $lesson->reading_time_minutes)
                                <span><i class="bi bi-clock me-1"></i>{{ $lesson->reading_time_minutes }} min read</span>
                            @endif

                            <span @class(['badge text-bg-success', 'd-none' => ! $isDone]) data-lesson-completed-badge>
                                <i class="bi bi-check-lg me-1"></i>Completed
                            </span>
                        </div>

                        <h1 class="page-title">{{ $lesson->title }}</h1>
                    </div>

                    {{-- Messages from "Mark as Complete" (sent without reloading the page). --}}
                    <div data-learn-messages aria-live="polite"></div>

                    @include('partials.courses.lesson-content', ['lesson' => $lesson])

                    {{-- A quiz lesson is completed by passing the quiz, so it has no "Mark as Complete". --}}
                    @if ($isEnrolled && $lesson->type !== 'quiz')
                        <div class="panel d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                            <div>
                                <div class="fw-bold" data-complete-title>
                                    {{ $isDone ? 'You completed this lesson.' : 'Finished this lesson?' }}
                                </div>
                                <div class="small text-body-secondary" data-complete-text>
                                    {{ $isDone ? 'Your progress is saved.' : 'Mark it as complete to update your progress.' }}
                                </div>
                            </div>

                            <button
                                type="button"
                                @class(['btn btn-success btn-lg', 'd-none' => ! $isDone])
                                disabled
                                data-complete-done
                            >
                                Completed ✓
                            </button>

                            @unless ($isDone)
                                <form
                                    method="POST"
                                    action="{{ route('courses.lessons.complete', [$course, $lesson]) }}"
                                    class="m-0"
                                    data-complete-form
                                >
                                    @csrf

                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-check2-circle me-1"></i>
                                        Mark as Complete
                                    </button>
                                </form>
                            @endunless
                        </div>
                    @endif

                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        @if ($previous)
                            <a href="{{ $lessonLink($previous) }}" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left me-1"></i>
                                Previous Lesson
                            </a>
                        @else
                            <span class="btn btn-outline-primary disabled" aria-disabled="true">
                                <i class="bi bi-arrow-left me-1"></i>
                                Previous Lesson
                            </span>
                        @endif

                        @if ($next)
                            <a href="{{ $lessonLink($next) }}" class="btn btn-primary">
                                Next Lesson
                                <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        @else
                            <span class="btn btn-primary disabled" aria-disabled="true">
                                Next Lesson
                                <i class="bi bi-arrow-right ms-1"></i>
                            </span>
                        @endif
                    </div>

                </div>

            </div>

        @else

            <x-ui.empty-state icon="bi-collection-play" title="No lessons available yet">
                The lessons of this course will appear here once they are added.

                <x-slot:action>
                    <a href="{{ $overviewUrl }}" class="btn btn-primary">
                        Back to Course
                    </a>
                </x-slot:action>
            </x-ui.empty-state>

        @endif

    </div>
</section>
@endsection

{{-- "Mark as Complete" without reloading the page. Without JavaScript the form still works as a normal POST. --}}
@if ($isEnrolled && $lesson)
    @push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-complete-form]');

            if (!form) {
                return;
            }

            const button = form.querySelector('button[type="submit"]');
            const messages = document.querySelector('[data-learn-messages]');

            // Same look as the flash messages of partials/alerts.blade.php.
            const showMessage = (type, text) => {
                const alert = document.createElement('div');
                alert.className = `alert alert-${type} alert-dismissible fade show d-flex align-items-center gap-2`;
                alert.setAttribute('role', 'alert');

                const icon = document.createElement('i');
                icon.className = type === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill';

                const body = document.createElement('div');
                body.textContent = text;

                const close = document.createElement('button');
                close.type = 'button';
                close.className = 'btn-close';
                close.setAttribute('data-bs-dismiss', 'alert');
                close.setAttribute('aria-label', 'Close');

                alert.append(icon, body, close);
                messages.replaceChildren(alert);
            };

            const showProgress = (data) => {
                document.querySelector('[data-course-progress-text]').textContent = `${data.progress}%`;
                document.querySelector('[data-course-progress-bar]').style.width = `${data.progress}%`;
                document.querySelector('[data-course-progress]').setAttribute('aria-valuenow', data.progress);

                // Sidebar: check mark on this lesson and the "done / total" of its section.
                const icon = document.querySelector(`[data-lesson-icon="${data.lessonId}"]`);
                if (icon) {
                    icon.className = 'bi bi-check-circle-fill text-success mt-1';
                }

                const sectionDone = document.querySelector(`[data-section-done="${data.sectionId}"]`);
                if (sectionDone) {
                    sectionDone.textContent = data.sectionCompletedLessons;
                }

                // This lesson: badge, texts and the disabled "Completed ✓" button.
                document.querySelector('[data-lesson-completed-badge]').classList.remove('d-none');
                document.querySelector('[data-complete-title]').textContent = 'You completed this lesson.';
                document.querySelector('[data-complete-text]').textContent = 'Your progress is saved.';
                document.querySelector('[data-complete-done]').classList.remove('d-none');
                form.remove();

                if (data.certificateUrl) {
                    document.querySelector('[data-certificate-link]').classList.remove('d-none');
                }
            };

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                button.disabled = true;

                let data;

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(form), // includes the CSRF token
                    });

                    data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(data.message || 'Your progress could not be saved. Please reload the page and try again.');
                    }
                } catch (error) {
                    button.disabled = false;
                    showMessage('danger', error.message);
                    return;
                }

                showProgress(data);

                if (data.nextUrl) {
                    // Same as before: move on to the next lesson (the message is shown there too).
                    showMessage('success', `${data.message} Opening the next lesson…`);
                    setTimeout(() => window.location.assign(data.nextUrl), 1500);
                } else {
                    showMessage('success', data.message);
                }
            });
        })();
    </script>
    @endpush
@endif
