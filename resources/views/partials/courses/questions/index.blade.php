@use('App\Models\QuizQuestion')
@use('Illuminate\Support\Str')

@php
    $totalPoints = $questions->sum('points');
@endphp

<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Quiz questions</span>
                <h1 class="page-title">{{ $lesson->title }}</h1>
                <p class="text-body-secondary">
                    {{ $course->title }}
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route($area . '.courses.content', $course) }}" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Course Content
                </a>

                @if ($questions->isNotEmpty())
                    <a href="{{ route($area . '.courses.lessons.questions.preview', [$course, $lesson]) }}"
                       class="btn btn-outline-primary">
                        <i class="bi bi-eye me-1"></i>
                        Preview Quiz
                    </a>
                @endif

                <a href="{{ route($area . '.courses.lessons.questions.create', [$course, $lesson]) }}"
                   class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>
                    Add Question
                </a>
            </div>
        </div>

        @if ($questions->isEmpty())
            <x-ui.empty-state icon="bi-patch-question" title="No questions yet">
                Add the first question of this quiz.
            </x-ui.empty-state>
        @else
            <div class="panel p-0 overflow-hidden">

                <div class="panel-header align-items-center">
                    <h2 class="panel-title mb-0">
                        {{ $questions->count() }} {{ Str::plural('question', $questions->count()) }}
                    </h2>

                    <span class="badge text-bg-primary fs-6" data-total-points>
                        Total: {{ $totalPoints }} {{ Str::plural('point', $totalPoints) }}
                    </span>
                </div>

                <p class="small text-body-secondary px-4 pt-3 mb-0">
                    Drag <i class="bi bi-grip-vertical"></i> to change the order,
                    or focus it and use the arrow keys.
                </p>

                <div class="px-4 pt-2" id="reorder-status" role="status" aria-live="polite"></div>

                <ul class="list-group list-group-flush" id="questions-list">
                    @foreach ($questions as $question)
                        <li class="list-group-item px-4 d-flex flex-wrap align-items-center justify-content-between gap-2"
                            data-question-id="{{ $question->id }}">

                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <button type="button"
                                        class="btn btn-sm btn-link text-body-secondary px-1"
                                        style="cursor: grab;"
                                        aria-label="Move question {{ $loop->iteration }}"
                                        data-drag-handle>
                                    <i class="bi bi-grip-vertical fs-5"></i>
                                </button>

                                <span class="fw-semibold" data-question-number>{{ $loop->iteration }}.</span>

                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">
                                        {{ Str::limit(html_entity_decode(strip_tags($question->question)), 90) }}
                                    </div>

                                    <div class="small text-body-secondary">
                                        {{ QuizQuestion::TYPES[$question->type] ?? strtoupper($question->type) }}
                                        · {{ $question->points }} {{ Str::plural('point', $question->points) }}
                                    </div>
                                </div>
                            </div>

                            <div class="text-nowrap">
                                <a href="{{ route($area . '.courses.lessons.questions.edit', [$course, $lesson, $question]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    Edit
                                </a>

                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteQuestionModal-{{ $question->id }}">
                                    Delete
                                </button>
                            </div>

                        </li>
                    @endforeach
                </ul>
            </div>

            @foreach ($questions as $question)
                @include('partials.courses.delete-modal', [
                    'id' => 'deleteQuestionModal-' . $question->id,
                    'title' => 'Delete Question ' . $loop->iteration,
                    'action' => route($area . '.courses.lessons.questions.destroy', [$course, $lesson, $question]),
                ])
            @endforeach

            {{-- Sent with fetch() after a question is dropped in a new place. --}}
            <form method="POST"
                  action="{{ route($area . '.courses.lessons.questions.reorder', [$course, $lesson]) }}"
                  id="reorder-form"
                  class="d-none">
                @csrf
                @method('PATCH')
            </form>
        @endif

    </div>
</section>

@if ($questions->isNotEmpty())
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        (() => {
            const list = document.getElementById('questions-list');
            const form = document.getElementById('reorder-form');
            const status = document.getElementById('reorder-status');

            const rows = () => Array.from(list.querySelectorAll('[data-question-id]'));
            const currentOrder = () => rows().map((row) => row.dataset.questionId);
            let savedOrder = currentOrder();

            const showStatus = (message, type) => {
                status.innerHTML = '';

                const alert = document.createElement('div');
                alert.className = 'alert alert-' + type + ' py-2 mb-0 small';
                alert.textContent = message;
                status.appendChild(alert);
            };

            const renumber = () => {
                rows().forEach((row, index) => {
                    row.querySelector('[data-question-number]').textContent = (index + 1) + '.';
                    row.querySelector('[data-drag-handle]').setAttribute('aria-label', 'Move question ' + (index + 1));
                });
            };

            // Puts the rows back in the last saved order.
            const restore = () => {
                savedOrder.forEach((id) => list.appendChild(list.querySelector('[data-question-id="' + id + '"]')));
                renumber();
            };

            const save = async () => {
                const order = currentOrder();

                if (order.join() === savedOrder.join()) {
                    return;
                }

                renumber();

                const body = new FormData(form); // includes the CSRF token and _method=PATCH
                order.forEach((id) => body.append('questions[]', id));

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body,
                    });
                    const data = await response.json().catch(() => ({}));

                    if (! response.ok) {
                        throw new Error(data.errors?.questions?.[0] ?? data.message ?? 'The new order could not be saved.');
                    }

                    savedOrder = order;
                    showStatus(data.message ?? 'Question order updated.', 'success');
                } catch (error) {
                    restore();
                    showStatus(error.message || 'The new order could not be saved.', 'danger');
                }
            };

            if (window.Sortable) {
                Sortable.create(list, {
                    handle: '[data-drag-handle]',
                    animation: 150,
                    ghostClass: 'bg-body-tertiary',
                    onEnd: save,
                });
            }

            // Keyboard: arrow up / down on the handle moves the question.
            list.addEventListener('keydown', (event) => {
                const handle = event.target.closest('[data-drag-handle]');

                if (! handle || ! ['ArrowUp', 'ArrowDown'].includes(event.key)) {
                    return;
                }

                event.preventDefault();

                const row = handle.closest('[data-question-id]');
                const sibling = event.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;

                if (! sibling) {
                    return;
                }

                list.insertBefore(row, event.key === 'ArrowUp' ? sibling : sibling.nextElementSibling);
                handle.focus();
                save();
            });
        })();
    </script>
    @endpush
@endif
