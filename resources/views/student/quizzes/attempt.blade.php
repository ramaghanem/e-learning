@extends('layouts.main')

@use('Illuminate\Support\Str')

@php
    $total = $answers->count();
    $answeredCount = $answers->filter->isAnswered()->count();
    $progress = $total > 0 ? (int) round($answeredCount * 100 / $total) : 0;

    // Open the first question not answered yet (e.g. when resuming).
    $startIndex = $answers->values()->search(fn ($answer) => ! $answer->isAnswered());
    $startIndex = $startIndex === false ? 0 : $startIndex;
@endphp

@section('title', $lesson->title . ' · Quiz')

@section('content')
<section class="page-section">
    <div class="container">

        {{-- Top bar --}}
        <div class="panel mb-4 py-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="me-auto min-w-0">
                    <span class="eyebrow mb-0">{{ $course->title }} · Attempt {{ $attempt->attempt_number }}</span>
                    <h1 class="page-title fs-3 mb-0 text-truncate">{{ $lesson->title }}</h1>
                </div>

                <span class="fw-semibold" data-question-counter aria-live="polite">
                    {{ $total }} {{ Str::plural('question', $total) }}
                </span>

                @if ($remainingMs !== null)
                    @php
                        $seconds = (int) ceil($remainingMs / 1000);
                    @endphp

                    {{-- Counts down from the time left on the server. --}}
                    <span class="badge fs-6 px-3 py-2 border text-bg-light"
                          role="timer"
                          aria-label="Time left"
                          data-timer
                          data-remaining-ms="{{ $remainingMs }}">
                        <i class="bi bi-stopwatch me-1"></i>
                        <span data-timer-text>{{ sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60) }}</span>
                    </span>

                    {{-- Warnings read out by screen readers (5 minutes, 1 minute, time's up). --}}
                    <span class="visually-hidden" aria-live="assertive" data-timer-announce></span>
                @endif

                <a href="{{ route('courses.learn', [$course, $lesson]) }}" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Lesson
                </a>

                {{-- Opens the confirmation dialog (without JavaScript it submits directly). --}}
                <button type="submit" form="quiz-submit-form" class="btn btn-success" data-open-submit>
                    <i class="bi bi-send-check me-1"></i>
                    Submit Quiz
                </button>
            </div>

            <div class="mt-3">
                <div class="d-flex justify-content-between small fw-semibold mb-1">
                    <span>Quiz progress</span>
                    <span data-progress-text>{{ $answeredCount }} of {{ $total }} answered</span>
                </div>

                <div class="progress" role="progressbar" aria-label="Quiz progress"
                     aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" data-progress>
                    <div class="progress-bar" style="width: {{ $progress }}%" data-progress-bar></div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- The questions (one at a time with JavaScript, all of them without) --}}
            <div class="col-lg-8">
                {{-- Errors of the auto-save (e.g. time's up) --}}
                <div data-quiz-messages aria-live="assertive"></div>

                {{-- Answers are saved one by one with fetch() as soon as they are chosen (auto-save). --}}
                <form id="quiz-attempt"
                      novalidate
                      onsubmit="return false;"
                      data-save-url="{{ route('courses.quizzes.answers', [$course, $lesson, $attempt]) }}">
                    @csrf

                    @foreach ($answers as $answer)
                        <fieldset class="panel mb-4"
                                  id="question-{{ $loop->iteration }}"
                                  data-question-panel
                                  data-index="{{ $loop->index }}"
                                  data-answer-id="{{ $answer->id }}"
                                  data-saved-option="{{ $answer->selected_option_id }}"
                                  tabindex="-1">
                            <legend class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100 fs-6 mb-3">
                                <span class="fw-semibold">Question {{ $loop->iteration }} of {{ $loop->count }}</span>
                                <span class="badge text-bg-light">
                                    {{ $answer->points }} {{ Str::plural('point', $answer->points) }}
                                </span>
                            </legend>

                            {{-- Cleaned when the question was saved (QuizQuestion::cleanHtml), then copied here. --}}
                            <div class="mb-3">{!! $answer->question !!}</div>

                            <div class="d-grid gap-2">
                                @foreach ($answer->options as $option)
                                    <label class="form-check border rounded px-3 py-2 ps-5 mb-0">
                                        <input type="radio"
                                               class="form-check-input"
                                               name="answers[{{ $answer->id }}]"
                                               value="{{ $option['id'] }}"
                                               data-answer-input
                                               @checked($answer->selected_option_id === $option['id'])>

                                        <span class="fw-semibold me-1">{{ chr(65 + $loop->index) }}.</span>
                                        {{ $option['text'] }}
                                    </label>
                                @endforeach
                            </div>

                            <div @class(['small mt-2', 'text-success' => $answer->isAnswered()])
                                 data-save-status role="status">
                                @if ($answer->isAnswered())
                                    <i class="bi bi-check-circle me-1"></i>Saved
                                @endif
                            </div>
                        </fieldset>
                    @endforeach

                    {{-- Previous / Next (shown by JavaScript) --}}
                    <div class="d-none justify-content-between gap-2" data-step-buttons>
                        <button type="button" class="btn btn-outline-primary" data-previous>
                            <i class="bi bi-arrow-left me-1"></i>
                            Previous
                        </button>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" data-next>
                                Next
                                <i class="bi bi-arrow-right ms-1"></i>
                            </button>

                            <button type="submit" form="quiz-submit-form" class="btn btn-success" data-open-submit>
                                <i class="bi bi-send-check me-1"></i>
                                Submit Quiz
                            </button>
                        </div>
                    </div>

                </form>

                {{-- Submitting: graded on the server. Kept apart from the answers form above. --}}
                <form method="POST"
                      action="{{ route('courses.quizzes.submit', [$course, $lesson, $attempt]) }}"
                      id="quiz-submit-form"
                      class="d-none">
                    @csrf
                </form>
            </div>

            {{-- Question navigation --}}
            <div class="col-lg-4">
                <div class="panel">
                    <h2 class="panel-title">Questions</h2>

                    <nav aria-label="Quiz questions">
                        <div class="d-flex flex-wrap gap-2" data-question-grid>
                            @foreach ($answers as $answer)
                                <a href="#question-{{ $loop->iteration }}"
                                   @class([
                                       'btn btn-sm fw-semibold',
                                       'btn-success' => $answer->isAnswered(),
                                       'btn-outline-secondary' => ! $answer->isAnswered(),
                                   ])
                                   style="width: 2.75rem;"
                                   data-jump="{{ $loop->index }}"
                                   aria-label="Question {{ $loop->iteration }}, {{ $answer->isAnswered() ? 'answered' : 'not answered' }}">
                                    {{ $loop->iteration }}
                                </a>
                            @endforeach
                        </div>
                    </nav>

                    <ul class="list-unstyled small text-body-secondary mt-3 mb-0">
                        <li><span class="badge text-bg-success me-1">&nbsp;</span> Answered</li>
                        <li><span class="badge border text-body me-1">&nbsp;</span> Not answered</li>
                        <li><span class="badge border border-primary border-2 me-1">&nbsp;</span> Current question</li>
                    </ul>
                </div>
            </div>

        </div>

    </div>
</section>

{{-- "Submit Quiz" confirmation --}}
<div class="modal fade" id="submitQuizModal" tabindex="-1" aria-labelledby="submitQuizModal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h2 class="modal-title fs-5" id="submitQuizModal-label">Submit Quiz</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="fw-semibold mb-2">Are you sure you want to submit?</p>
                <p class="mb-3" data-submit-summary></p>

                <div class="d-none" data-submit-unanswered>
                    <p class="text-danger mb-2">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        These questions are not answered yet:
                    </p>
                    <ul class="list-unstyled d-flex flex-wrap gap-2 mb-3" data-unanswered-list></ul>
                </div>

                <p class="small text-body-secondary mb-0">
                    You cannot change your answers after submitting.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">
                    Go Back
                </button>

                <button type="button" class="btn btn-success" data-confirm-submit>
                    Submit Anyway
                </button>
            </div>

        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Last minute of the timer: red and flashing. */
    @keyframes quiz-timer-flash {
        50% { opacity: 0.35; }
    }

    .quiz-timer-flash {
        animation: quiz-timer-flash 1s steps(1) infinite;
    }

    @media (prefers-reduced-motion: reduce) {
        .quiz-timer-flash {
            animation: none;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    (() => {
        const panels = Array.from(document.querySelectorAll('[data-question-panel]'));
        const jumps = Array.from(document.querySelectorAll('[data-jump]'));
        const counter = document.querySelector('[data-question-counter]');
        const progress = document.querySelector('[data-progress]');
        const progressBar = document.querySelector('[data-progress-bar]');
        const progressText = document.querySelector('[data-progress-text]');
        const stepButtons = document.querySelector('[data-step-buttons]');
        const previousButton = document.querySelector('[data-previous]');
        const nextButton = document.querySelector('[data-next]');
        const total = panels.length;
        let current = {{ $startIndex }};

        const isAnswered = (panel) => panel.querySelector('[data-answer-input]:checked') !== null;

        // Grid colours and progress bar, from the options chosen on the page.
        const refresh = () => {
            let answered = 0;

            panels.forEach((panel, index) => {
                const done = isAnswered(panel);
                const jump = jumps[index];
                answered += done ? 1 : 0;

                jump.classList.toggle('btn-success', done);
                jump.classList.toggle('btn-outline-secondary', ! done);
                jump.classList.toggle('border-primary', index === current);
                jump.classList.toggle('border-3', index === current);
                jump.setAttribute('aria-label', 'Question ' + (index + 1) + ', ' + (done ? 'answered' : 'not answered')
                    + (index === current ? ', current question' : ''));

                if (index === current) {
                    jump.setAttribute('aria-current', 'step');
                } else {
                    jump.removeAttribute('aria-current');
                }
            });

            const percent = total > 0 ? Math.round(answered * 100 / total) : 0;
            progressBar.style.width = percent + '%';
            progress.setAttribute('aria-valuenow', percent);
            progressText.textContent = answered + ' of ' + total + ' answered';
        };

        // Shows one question at a time.
        const show = (index, moveFocus = true) => {
            current = Math.max(0, Math.min(total - 1, index));

            panels.forEach((panel, i) => panel.classList.toggle('d-none', i !== current));
            counter.textContent = 'Question ' + (current + 1) + ' of ' + total;
            previousButton.disabled = current === 0;
            nextButton.disabled = current === total - 1;
            refresh();

            if (moveFocus) {
                panels[current].focus();
            }
        };

        previousButton.addEventListener('click', () => show(current - 1));
        nextButton.addEventListener('click', () => show(current + 1));

        jumps.forEach((jump, index) => jump.addEventListener('click', (event) => {
            event.preventDefault();
            show(index);
        }));

        // ---------- Auto-save ----------

        const form = document.getElementById('quiz-attempt');
        const saveUrl = form.dataset.saveUrl;
        const token = form.querySelector('input[name="_token"]').value;
        const messages = document.querySelector('[data-quiz-messages]');
        const queues = new Map();   // one queue per question, so its saves arrive in order
        let closed = false;          // the attempt was submitted or its time ran out

        const setStatus = (panel, html, className) => {
            const status = panel.querySelector('[data-save-status]');
            status.className = 'small mt-2 ' + className;
            status.innerHTML = html;
        };

        // Puts back the option the server has saved.
        const restoreSaved = (panel) => {
            panel.querySelectorAll('[data-answer-input]').forEach((input) => {
                input.checked = input.value === panel.dataset.savedOption;
            });
            refresh();
        };

        const showMessage = (text) => {
            messages.innerHTML = '';
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.setAttribute('role', 'alert');
            alert.textContent = text;
            messages.appendChild(alert);
        };

        const save = (panel, optionId) => {
            const answerId = panel.dataset.answerId;
            panel.dataset.pendingOption = optionId;   // the latest choice for this question
            setStatus(panel, 'Saving…', 'text-body-secondary');

            const send = async () => {
                if (closed) {
                    return;
                }

                const isLatest = () => panel.dataset.pendingOption === optionId;
                const body = new FormData();
                body.append('_token', token);
                body.append('_method', 'PATCH');
                body.append('answer_id', answerId);
                body.append('option_id', optionId);

                try {
                    const response = await fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body,
                    });
                    const data = await response.json().catch(() => ({}));

                    // Submitted, or the time ran out: nothing more can be saved.
                    if (response.status === 409 && data.redirect) {
                        closed = true;
                        form.querySelectorAll('[data-answer-input]').forEach((input) => input.disabled = true);
                        showMessage(data.message + ' Taking you to your results…');
                        setTimeout(() => window.location.assign(data.redirect), 2500);
                        return;
                    }

                    if (! response.ok) {
                        throw new Error(data.errors?.option_id?.[0] ?? data.errors?.answer_id?.[0] ?? data.message ?? '');
                    }

                    panel.dataset.savedOption = String(data.optionId);

                    if (isLatest()) {
                        setStatus(panel, '<i class="bi bi-check-circle me-1"></i>Saved', 'text-success');
                    }
                } catch (error) {
                    if (isLatest()) {
                        restoreSaved(panel);
                        setStatus(
                            panel,
                            '<i class="bi bi-exclamation-triangle me-1"></i>',
                            'text-danger'
                        );
                        panel.querySelector('[data-save-status]').append(
                            (error.message || 'Your answer was not saved. Check your connection.') + ' Please choose it again.'
                        );
                    }
                }
            };

            const queue = (queues.get(answerId) ?? Promise.resolve()).then(send);
            queues.set(answerId, queue);
        };

        form.addEventListener('change', (event) => {
            if (! event.target.matches('[data-answer-input]')) {
                return;
            }

            refresh();
            save(event.target.closest('[data-question-panel]'), event.target.value);
        });

        // ---------- Submit ----------

        const submitForm = document.getElementById('quiz-submit-form');
        const submitButtons = document.querySelectorAll('[data-open-submit]');
        const modalElement = document.getElementById('submitQuizModal');
        const modal = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
        const confirmButton = modalElement.querySelector('[data-confirm-submit]');
        const summary = modalElement.querySelector('[data-submit-summary]');
        const unansweredBox = modalElement.querySelector('[data-submit-unanswered]');
        const unansweredList = modalElement.querySelector('[data-unanswered-list]');
        let submitting = false;

        // Lets the answers still being saved finish, then sends the attempt to be graded.
        const submitNow = async () => {
            if (submitting) {
                return;
            }

            submitting = true;
            form.querySelectorAll('[data-answer-input]').forEach((input) => input.disabled = true);
            submitButtons.forEach((button) => button.disabled = true);
            confirmButton.disabled = true;

            await Promise.allSettled(Array.from(queues.values()));
            closed = true;
            submitForm.submit();
        };

        // The confirmation: how many are answered, and which are not.
        submitButtons.forEach((button) => button.addEventListener('click', (event) => {
            if (! modal) {
                return;   // no dialog available: the form submits directly
            }

            event.preventDefault();

            const unanswered = panels
                .map((panel, index) => isAnswered(panel) ? null : index)
                .filter((index) => index !== null);

            summary.textContent = (total - unanswered.length) + ' of ' + total + ' questions answered.';
            unansweredList.innerHTML = '';

            unanswered.forEach((index) => {
                const item = document.createElement('li');
                const link = document.createElement('button');
                link.type = 'button';
                link.className = 'btn btn-sm btn-outline-danger';
                link.textContent = 'Question ' + (index + 1);
                link.addEventListener('click', () => {
                    modal.hide();
                    show(index);
                });
                item.appendChild(link);
                unansweredList.appendChild(item);
            });

            unansweredBox.classList.toggle('d-none', unanswered.length === 0);
            confirmButton.textContent = unanswered.length ? 'Submit Anyway' : 'Submit Quiz';
            modal.show();
        }));

        confirmButton.addEventListener('click', () => {
            modal.hide();
            submitNow();
        });

        // ---------- Timer ----------

        const timer = document.querySelector('[data-timer]');

        if (timer) {
            const timerText = timer.querySelector('[data-timer-text]');
            const announce = document.querySelector('[data-timer-announce]');
            // Measured from the time left on the server, so a wrong clock on the
            // student's computer does not matter.
            const deadline = performance.now() + Number(timer.dataset.remainingMs);
            let warnedFive = false;
            let warnedOne = false;
            let interval = null;

            const format = (seconds) =>
                String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');

            // Time's up: stop answering and submit automatically (the server
            // records it as time's up).
            const timeUp = () => {
                modal?.hide();
                showMessage("Time's up! Submitting your answers…");
                announce.textContent = "Time's up!";
                submitNow();
            };

            const tick = () => {
                const msLeft = Math.max(0, deadline - performance.now());
                const seconds = Math.ceil(msLeft / 1000);
                const warning = seconds <= 5 * 60;
                const danger = seconds <= 60;

                timerText.textContent = format(seconds);

                timer.classList.toggle('text-bg-light', ! warning);
                timer.classList.toggle('text-bg-warning', warning && ! danger);
                timer.classList.toggle('text-bg-danger', danger);
                timer.classList.toggle('quiz-timer-flash', danger && msLeft > 0);

                if (warning && ! danger && ! warnedFive) {
                    warnedFive = true;
                    announce.textContent = 'Less than 5 minutes left.';
                }

                if (danger && msLeft > 0 && ! warnedOne) {
                    warnedOne = true;
                    announce.textContent = 'Less than 1 minute left!';
                }

                if (msLeft <= 0) {
                    clearInterval(interval);
                    timer.setAttribute('aria-label', "Time's up");
                    timeUp();
                }
            };

            // Checked 4 times a second, so the display changes right on each second.
            interval = setInterval(tick, 250);
            tick();
        }

        stepButtons.classList.remove('d-none');
        stepButtons.classList.add('d-flex');
        show(current, false);
    })();
</script>
@endpush
