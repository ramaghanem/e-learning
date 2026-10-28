@php
    $sections = $course->sections ?? collect();

    $lessonMinutes = fn ($lesson) => (int) match ($lesson->type) {
        'video' => $lesson->duration_minutes,
        'article' => $lesson->reading_time_minutes,
        default => 0,
    };

    $typeIcon = fn ($lesson) => match ($lesson->type) {
        'article' => 'bi-file-earmark-text',
        'quiz' => 'bi-patch-question',
        default => 'bi-play-circle',
    };
@endphp

<div class="panel p-0 course-content learn-sidebar sticky-panel">

    <div class="panel-header">
        <div>
            <span class="eyebrow mb-1">Course Curriculum</span>
            <h2 class="panel-title mb-0">{{ $course->title }}</h2>
        </div>
    </div>

    <div class="accordion accordion-flush">

        @foreach ($sections as $section)

            @php
                $collapseId = 'learn-section-' . $section->id;
                $hasCurrent = $section->lessons->contains('id', $currentLesson->id);
                $doneInSection = $section->lessons->filter(fn ($item) => $completedLessonIds->contains($item->id))->count();
            @endphp

            <div class="accordion-item">

                <h3 class="accordion-header">
                    <button
                        @class(['accordion-button px-3 gap-2', 'collapsed' => ! $hasCurrent])
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#{{ $collapseId }}"
                        aria-expanded="{{ $hasCurrent ? 'true' : 'false' }}"
                        aria-controls="{{ $collapseId }}"
                    >
                        <span class="flex-grow-1 fw-semibold">
                            Section {{ $loop->iteration }}: {{ $section->title }}
                        </span>

                        <span class="small text-body-secondary text-nowrap">
                            <span data-section-done="{{ $section->id }}">{{ $doneInSection }}</span>/{{ $section->lessons->count() }}
                        </span>
                    </button>
                </h3>

                <div id="{{ $collapseId }}" @class(['accordion-collapse collapse', 'show' => $hasCurrent])>
                    <ul class="list-group list-group-flush">

                        @forelse ($section->lessons as $item)

                            @php
                                $isCurrent = $item->id === $currentLesson->id;
                                $isDone = $completedLessonIds->contains($item->id);
                                $isOpen = $canOpenLesson($item);
                            @endphp

                            <li @class([
                                'list-group-item px-3 d-flex align-items-start gap-2',
                                'list-group-item-action position-relative' => $isOpen && ! $isCurrent,
                                'learn-lesson-current' => $isCurrent,
                                'text-body-secondary' => ! $isOpen,
                            ])>

                                @if ($isDone)
                                    <i class="bi bi-check-circle-fill text-success mt-1" aria-hidden="true" data-lesson-icon="{{ $item->id }}"></i>
                                @elseif (! $isOpen)
                                    <i class="bi bi-lock mt-1" aria-hidden="true" data-lesson-icon="{{ $item->id }}"></i>
                                @else
                                    <i class="bi {{ $typeIcon($item) }} text-accent mt-1" aria-hidden="true" data-lesson-icon="{{ $item->id }}"></i>
                                @endif

                                <span class="flex-grow-1 small">
                                    @if ($isOpen && ! $isCurrent)
                                        <a href="{{ $lessonLink($item) }}" class="stretched-link text-reset text-decoration-none">
                                            {{ $item->title }}
                                        </a>
                                    @else
                                        <span @if ($isCurrent) aria-current="page" @endif>{{ $item->title }}</span>
                                    @endif

                                    <span class="visually-hidden">
                                        @if ($isDone) (completed) @elseif (! $isOpen) (locked) @endif
                                    </span>
                                </span>

                                @if ($lessonMinutes($item))
                                    <span class="small text-body-secondary text-nowrap">
                                        {{ $lessonMinutes($item) }} min
                                    </span>
                                @endif

                            </li>

                        @empty

                            <li class="list-group-item px-3 small text-body-secondary">
                                No lessons in this section yet.
                            </li>

                        @endforelse

                    </ul>
                </div>

            </div>

        @endforeach

    </div>

</div>
