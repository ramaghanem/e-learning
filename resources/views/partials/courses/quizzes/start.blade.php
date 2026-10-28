@use('Illuminate\Support\Str')

@php
    $isEnrolled = $isEnrolled ?? false;
    $quizAttempts = $quizAttempts ?? collect();
    $openAttempt = $openAttempt ?? null;
    $area = $area ?? null;

    $questionCount = $lesson->questions_count ?? $lesson->questions()->count();
    $totalPoints = (int) ($lesson->questions_sum_points ?? $lesson->questions()->sum('points'));
    $timeLimit = $lesson->quiz_time_limit_minutes;
    $maxAttempts = $lesson->quiz_max_attempts;

    $usedAttempts = $quizAttempts->count();
    $attemptsLeft = $lesson->quizAttemptsLeft($usedAttempts);
    $canStart = $isEnrolled && ! $openAttempt && $questionCount > 0 && $attemptsLeft !== 0;
    $hasPassed = $quizAttempts->contains(fn ($attempt) => $attempt->passed);


    $attemptNumber = $openAttempt?->attempt_number ?? $usedAttempts + 1;
@endphp

<div data-quiz-start>

    @if (filled($lesson->content))
        <h2 class="panel-title">Instructions</h2>
        <div class="mb-4">{!! nl2br(e($lesson->content)) !!}</div>
    @endif

    <h2 class="panel-title">Quiz details</h2>

    <dl class="row mb-3">
        <dt class="col-sm-4">Questions</dt>
        <dd class="col-sm-8">{{ $questionCount }}</dd>

        <dt class="col-sm-4">Total points</dt>
        <dd class="col-sm-8">{{ $totalPoints }}</dd>

        <dt class="col-sm-4">Time limit</dt>
        <dd class="col-sm-8">
            {{ $timeLimit ? $timeLimit . ' ' . Str::plural('minute', $timeLimit) : 'No time limit' }}
        </dd>

        <dt class="col-sm-4">Passing score</dt>
        <dd class="col-sm-8">{{ $lesson->quiz_passing_score }}%</dd>

        <dt class="col-sm-4">Attempts</dt>
        <dd class="col-sm-8" data-quiz-attempts>
            @if (! $isEnrolled)
                {{ $maxAttempts ? $maxAttempts . ' ' . Str::plural('attempt', $maxAttempts) . ' allowed' : 'Unlimited attempts' }}
            @elseif ($maxAttempts === null)
                Attempt {{ $attemptNumber }} · Unlimited attempts
            @elseif ($openAttempt || $attemptsLeft > 0)
                Attempt {{ $attemptNumber }} of {{ $maxAttempts }}
            @else
                All {{ $maxAttempts }} {{ Str::plural('attempt', $maxAttempts) }} used
            @endif
        </dd>
    </dl>

    @if ($timeLimit && $questionCount > 0)
        <div class="alert alert-warning d-flex gap-2" role="note">
            <i class="bi bi-stopwatch"></i>
            <div>
                <strong>You have {{ $timeLimit }} {{ Str::plural('minute', $timeLimit) }}</strong>
                to finish this quiz. The timer starts when you click Start Quiz
                and keeps running if you leave the page.
            </div>
        </div>
    @endif

    @if ($hasPassed)
        <div class="alert alert-success d-flex gap-2" role="status">
            <i class="bi bi-trophy"></i>
            <div>You passed this quiz.</div>
        </div>
    @endif

    {{-- What the student can do now --}}
    @if ($questionCount === 0)
        <x-ui.empty-state icon="bi-patch-question" title="This quiz has no questions yet" class="border-0 py-3" />
    @elseif ($area)
        {{-- Admin / Instructor course preview --}}
        <a href="{{ route($area . '.courses.lessons.questions.preview', [$course, $lesson]) }}" class="btn btn-primary">
            <i class="bi bi-eye me-1"></i>
            Preview Quiz
        </a>
    @elseif (! $isEnrolled)
        <div class="alert alert-info mb-0" role="status">
            Enroll in this course to take this quiz.
        </div>
    @elseif ($openAttempt)
        <div class="d-flex flex-wrap align-items-center gap-3">
            <form method="POST" action="{{ route('courses.quizzes.start', [$course, $lesson]) }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-play-fill me-1"></i>
                    Resume Quiz
                </button>
            </form>

            <span class="text-body-secondary">
                Attempt {{ $openAttempt->attempt_number }} is in progress.
                @if ($openAttempt->expires_at)
                    Time left: about {{ max(1, (int) ceil(now()->diffInSeconds($openAttempt->expires_at) / 60)) }} min.
                @endif
            </span>
        </div>
    @elseif ($canStart)
        <form method="POST" action="{{ route('courses.quizzes.start', [$course, $lesson]) }}" class="m-0">
            @csrf
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-play-fill me-1"></i>
                Start Quiz
            </button>
        </form>
    @else
        <div class="alert alert-secondary mb-0" role="status">
            <i class="bi bi-slash-circle me-1"></i>
            You have used all {{ $maxAttempts }} {{ Str::plural('attempt', $maxAttempts) }} for this quiz,
            so you cannot start a new one.
        </div>
    @endif

    @if ($quizAttempts->isNotEmpty())
        <h2 class="panel-title mt-4">Previous attempts</h2>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Attempt</th>
                        <th scope="col">Date</th>
                        <th scope="col">Score</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Results</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quizAttempts->sortByDesc('attempt_number') as $attempt)
                        <tr>
                            <td>#{{ $attempt->attempt_number }}</td>
                            <td>{{ ($attempt->submitted_at ?? $attempt->started_at)->format('M j, Y g:i A') }}</td>
                            <td>
                                @if ($attempt->isSubmitted())
                                    {{ $attempt->score }} / {{ $attempt->total_points }}
                                    <span class="text-body-secondary">({{ $attempt->percentageLabel() }})</span>
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($attempt->isInProgress())
                                    <span class="badge text-bg-warning">In progress</span>
                                @elseif ($attempt->passed)
                                    <span class="badge text-bg-success">Passed</span>
                                @else
                                    <span class="badge text-bg-danger">Failed</span>
                                @endif

                                @if ($attempt->timed_out)
                                    <span class="badge text-bg-light">Time's up</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($attempt->isSubmitted() && $isEnrolled)
                                    <a href="{{ route('courses.quizzes.results', [$course, $lesson, $attempt]) }}"
                                       class="btn btn-sm btn-outline-primary"
                                       aria-label="View the results of attempt {{ $attempt->attempt_number }}">
                                        View Results
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
