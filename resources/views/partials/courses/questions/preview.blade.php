@use('Illuminate\Support\Str')

@php
    $totalPoints = $questions->sum('points');
    $questionsUrl = route($area . '.courses.lessons.questions.index', [$course, $lesson]);
@endphp

<section class="page-section">
    <div class="container">

        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 mb-4" role="status">
            <i class="bi bi-eye-fill"></i>

            <div class="me-auto">
                <strong>Preview Mode</strong>
                <span class="d-none d-sm-inline">— try the quiz as a student. Your answers are not saved.</span>
            </div>

            <a href="{{ $questionsUrl }}" class="btn btn-sm btn-outline-dark">
                <i class="bi bi-x-lg me-1"></i>
                Exit Preview
            </a>
        </div>

        <div class="page-heading">
            <div>
                <span class="eyebrow">Quiz</span>
                <h1 class="page-title">{{ $lesson->title }}</h1>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 small text-body-secondary">
                    <span class="badge text-bg-light">
                        {{ $questions->count() }} {{ Str::plural('question', $questions->count()) }}
                    </span>
                    <span class="badge text-bg-light">
                        {{ $totalPoints }} {{ Str::plural('point', $totalPoints) }}
                    </span>
                </div>
            </div>
        </div>

        @if ($questions->isEmpty())
            <x-ui.empty-state icon="bi-patch-question" title="This quiz has no questions yet">
                Add questions to preview the quiz.

                <x-slot:action>
                    <a href="{{ $questionsUrl }}" class="btn btn-primary">
                        Back to Questions
                    </a>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <div class="row g-4">
                <div class="col-lg-9">

                    {{-- No action and no method: the answers never leave the browser. --}}
                    <form id="quiz-preview" novalidate>

                        @foreach ($questions as $question)
                            <fieldset class="panel mb-4" data-question data-points="{{ $question->points }}">
                                <legend class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100 fs-6 mb-3">
                                    <span class="fw-semibold">
                                        Question {{ $loop->iteration }} of {{ $loop->count }}
                                    </span>

                                    <span class="badge text-bg-light">
                                        {{ $question->points }} {{ Str::plural('point', $question->points) }}
                                    </span>
                                </legend>

                                {{-- Cleaned when the question was saved (QuizQuestion::cleanHtml). --}}
                                <div class="mb-3">{!! $question->question !!}</div>

                                <div class="d-grid gap-2">
                                    @foreach ($question->options as $option)
                                        <label class="form-check border rounded px-3 py-2 ps-5 mb-0"
                                               data-option
                                               @if ($option->is_correct) data-correct @endif>
                                            <input type="radio"
                                                   class="form-check-input"
                                                   name="question-{{ $question->id }}"
                                                   value="{{ $option->id }}">

                                            <span class="fw-semibold me-1">{{ chr(65 + $loop->index) }}.</span>
                                            {{ $option->text }}
                                            <i class="bi ms-1 d-none" data-option-icon></i>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="small fw-semibold mt-3 d-none" data-feedback></div>
                            </fieldset>
                        @endforeach

                        <div class="panel mb-4 d-none" id="quiz-result" tabindex="-1" role="status" aria-live="polite">
                            <h2 class="panel-title">Preview result</h2>
                            <p class="fs-5 fw-semibold mb-1" data-result-score></p>
                            <p class="text-body-secondary mb-0" data-result-summary></p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary px-4" id="check-answers">
                                <i class="bi bi-check2-circle me-1"></i>
                                Check Answers
                            </button>

                            <button type="button" class="btn btn-outline-primary d-none" id="try-again">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Try Again
                            </button>

                            <a href="{{ $questionsUrl }}" class="btn btn-outline-secondary ms-auto">
                                <i class="bi bi-x-lg me-1"></i>
                                Exit Preview
                            </a>
                        </div>

                    </form>

                </div>
            </div>
        @endif

    </div>
</section>

@if ($questions->isNotEmpty())
    @push('scripts')
    <script>
        (() => {
            const form = document.getElementById('quiz-preview');
            const result = document.getElementById('quiz-result');
            const checkButton = document.getElementById('check-answers');
            const tryAgainButton = document.getElementById('try-again');
            const questions = Array.from(form.querySelectorAll('[data-question]'));

            const plural = (count, word) => count + ' ' + word + (count === 1 ? '' : 's');

            const setFeedback = (element, text, className) => {
                element.textContent = text;
                element.className = 'small fw-semibold mt-3 ' + className;
            };

            // Checks the answers in the browser only. Nothing is sent to the server.
            form.addEventListener('submit', (event) => {
                event.preventDefault();

                let score = 0;
                let total = 0;
                let correctCount = 0;
                let unanswered = 0;

                questions.forEach((question) => {
                    const points = Number(question.dataset.points);
                    const options = Array.from(question.querySelectorAll('[data-option]'));
                    const chosen = options.find((option) => option.querySelector('input').checked);
                    const correct = options.find((option) => option.hasAttribute('data-correct'));
                    const correctLetter = String.fromCharCode(65 + options.indexOf(correct));
                    const feedback = question.querySelector('[data-feedback]');

                    total += points;

                    options.forEach((option) => {
                        const icon = option.querySelector('[data-option-icon]');
                        option.querySelector('input').disabled = true;

                        if (option === correct) {
                            option.classList.add('border-success', 'bg-success-subtle');
                            icon.className = 'bi bi-check-circle-fill text-success ms-1';
                        } else if (option === chosen) {
                            option.classList.add('border-danger', 'bg-danger-subtle');
                            icon.className = 'bi bi-x-circle-fill text-danger ms-1';
                        }
                    });

                    if (! chosen) {
                        unanswered++;
                        setFeedback(feedback, 'Not answered. The correct answer is ' + correctLetter + '.', 'text-body-secondary');
                    } else if (chosen === correct) {
                        score += points;
                        correctCount++;
                        setFeedback(feedback, 'Correct! +' + plural(points, 'point'), 'text-success');
                    } else {
                        setFeedback(feedback, 'Incorrect. The correct answer is ' + correctLetter + '.', 'text-danger');
                    }
                });

                const percent = total > 0 ? Math.round((score / total) * 100) : 0;

                result.querySelector('[data-result-score]').textContent =
                    'Score: ' + score + ' / ' + plural(total, 'point') + ' (' + percent + '%)';
                result.querySelector('[data-result-summary]').textContent =
                    correctCount + ' of ' + plural(questions.length, 'question') + ' answered correctly'
                    + (unanswered ? ', ' + unanswered + ' not answered.' : '.')
                    + ' This is a preview: the result is not saved.';

                result.classList.remove('d-none');
                checkButton.classList.add('d-none');
                tryAgainButton.classList.remove('d-none');
                result.focus();
            });

            // Clears the answers and the marks to try the quiz again.
            tryAgainButton.addEventListener('click', () => {
                form.reset();

                questions.forEach((question) => {
                    question.querySelectorAll('[data-option]').forEach((option) => {
                        option.classList.remove('border-success', 'bg-success-subtle', 'border-danger', 'bg-danger-subtle');
                        option.querySelector('[data-option-icon]').className = 'bi ms-1 d-none';
                        option.querySelector('input').disabled = false;
                    });

                    question.querySelector('[data-feedback]').className = 'small fw-semibold mt-3 d-none';
                });

                result.classList.add('d-none');
                tryAgainButton.classList.add('d-none');
                checkButton.classList.remove('d-none');
                questions[0].querySelector('input').focus();
            });
        })();
    </script>
    @endpush
@endif
