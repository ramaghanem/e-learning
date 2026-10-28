@extends('layouts.main')

@use('Illuminate\Support\Str')

@php
    $passed = (bool) $attempt->passed;
    $canRetake = ! $passed && $attemptsLeft !== 0;
    $reviewUrl = $reviewLesson
        ? route('courses.learn', [$course, $reviewLesson])
        : route('courses.show', $course);
@endphp

@section('title', 'Results · ' . $lesson->title)

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">{{ $course->title }} · Attempt {{ $attempt->attempt_number }}</span>
                <h1 class="page-title">{{ $lesson->title }}: Results</h1>
            </div>

            <a href="{{ route('courses.learn', [$course, $lesson]) }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Quiz
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-9">

                {{-- Score --}}
                <div class="panel text-center mb-4" data-result>
                    <span @class(['badge fs-6 px-3 py-2 mb-3', 'text-bg-success' => $passed, 'text-bg-danger' => ! $passed])>
                        {{ $passed ? 'PASSED' : 'FAILED' }}
                    </span>

                    <p class="display-6 fw-bold mb-1">
                        You scored {{ $attempt->score }} out of {{ $attempt->total_points }}
                        {{ Str::plural('point', $attempt->total_points) }}
                    </p>

                    <p @class(['display-4 fw-bold mb-3', 'text-success' => $passed, 'text-danger' => ! $passed])>
                        {{ $attempt->percentageLabel() }}
                    </p>

                    <ul class="list-inline text-body-secondary mb-0">
                        <li class="list-inline-item me-3">
                            <i class="bi bi-clock me-1"></i>
                            Completed in {{ $attempt->durationLabel() }}
                        </li>
                        <li class="list-inline-item me-3">
                            <i class="bi bi-flag me-1"></i>
                            Passing score was {{ $attempt->passing_score }}%
                        </li>
                        @if ($attempt->timed_out)
                            <li class="list-inline-item">
                                <i class="bi bi-stopwatch me-1"></i>
                                Time's up: submitted automatically
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- What next --}}
                @if ($passed)
                    <div class="panel mb-4 border-success">
                        <h2 class="panel-title text-success">
                            <i class="bi bi-trophy me-1"></i>
                            Congratulations!
                        </h2>
                        <p>
                            You passed this quiz, and the lesson is now marked as complete.
                            @if ($enrollment->isCompleted())
                                You have completed the whole course!
                            @endif
                        </p>

                        <div class="d-flex flex-wrap gap-2">
                            @if ($nextLesson)
                                <a href="{{ route('courses.learn', [$course, $nextLesson]) }}" class="btn btn-success">
                                    Continue to Next Lesson
                                    <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            @else
                                <a href="{{ route('courses.show', $course) }}" class="btn btn-outline-primary">
                                    Back to Course
                                </a>
                            @endif

                            @if ($enrollment->isCompleted())
                                <a href="{{ route('courses.certificate', $course) }}" class="btn btn-primary">
                                    <i class="bi bi-award me-1"></i>
                                    View Certificate
                                </a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="panel mb-4">
                        <h2 class="panel-title">Don't give up!</h2>
                        <p class="mb-2">Review the material and try again.</p>

                        <p class="fw-semibold" data-attempts-left>
                            @if ($attemptsLeft === null)
                                You can retake this quiz as many times as you need.
                            @elseif ($attemptsLeft > 0)
                                You have {{ $attemptsLeft }} {{ Str::plural('attempt', $attemptsLeft) }} left.
                            @else
                                You have no attempts left for this quiz.
                            @endif
                        </p>

                        <div class="d-flex flex-wrap gap-2">
                            @if ($canRetake)
                                <form method="POST" action="{{ route('courses.quizzes.start', [$course, $lesson]) }}" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                                        Retake Quiz
                                    </button>
                                </form>
                            @endif

                            <a href="{{ $reviewUrl }}" class="btn btn-outline-primary">
                                <i class="bi bi-book me-1"></i>
                                Review Course Material
                            </a>
                        </div>
                    </div>
                @endif

                {{-- Review Answers --}}
                @if ($showReview)
                    <h2 class="panel-title mb-3">Review Answers</h2>

                    @foreach ($answers as $answer)
                        @php
                            $isCorrect = (bool) $answer->is_correct;
                        @endphp

                        <div @class(['panel mb-3 border-start border-4', 'border-success' => $isCorrect, 'border-danger' => ! $isCorrect])
                             data-review-question>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <span class="fw-semibold">
                                    @if ($isCorrect)
                                        <i class="bi bi-check-circle-fill text-success me-1" aria-hidden="true"></i>
                                        <span class="visually-hidden">Correct:</span>
                                    @else
                                        <i class="bi bi-x-circle-fill text-danger me-1" aria-hidden="true"></i>
                                        <span class="visually-hidden">Incorrect:</span>
                                    @endif
                                    Question {{ $answer->position }}
                                </span>

                                <span @class(['badge', 'text-bg-success' => $isCorrect, 'text-bg-light' => ! $isCorrect])>
                                    {{ $answer->points_earned }} / {{ $answer->points }}
                                    {{ Str::plural('point', $answer->points) }}
                                </span>
                            </div>

                            {{-- Copied from the question when the attempt started (cleaned when it was saved). --}}
                            <div class="mb-3">{!! $answer->question !!}</div>

                            <dl class="row mb-0">
                                <dt class="col-sm-4">Your answer</dt>
                                <dd @class(['col-sm-8', 'text-success' => $isCorrect, 'text-danger' => ! $isCorrect])>
                                    {{ $answer->selectedOptionText() ?? 'Not answered' }}
                                </dd>

                                <dt class="col-sm-4">Correct answer</dt>
                                <dd class="col-sm-8 text-success mb-0">
                                    {{ $answer->correctOptionText() }}
                                </dd>
                            </dl>
                        </div>
                    @endforeach
                @else
                    <div class="alert alert-secondary" role="status">
                        <i class="bi bi-eye-slash me-1"></i>
                        Answer review is not available for this quiz. Only your overall score is shown.
                    </div>
                @endif

            </div>
        </div>

    </div>
</section>
@endsection
