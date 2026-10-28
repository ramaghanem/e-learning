@php
    $sections = $course->sections ?? collect();
    $lessonCount = $sections->sum(fn ($section) => $section->lessons->count());

    $isEnrolled = $isEnrolled ?? false;
    $canOpen = fn ($lesson) => $isEnrolled || $lesson->is_free_preview;
    $lessonUrl = fn ($lesson) => route('courses.learn', [$course, $lesson]);

    // 75 -> "1h 15m", 40 -> "40 min"
    $formatMinutes = fn (int $total) => $total >= 60
        ? intdiv($total, 60) . 'h' . ($total % 60 ? ' ' . ($total % 60) . 'm' : '')
        : $total . ' min';

    $lessonMinutes = fn ($lesson) => (int) match ($lesson->type) {
        'video' => $lesson->duration_minutes,
        'article' => $lesson->reading_time_minutes,
        default => 0,
    };
@endphp

<div class="panel p-0 overflow-hidden mb-4 course-content">

    <div class="panel-header">
        <h2 class="panel-title mb-0">
            Course Content
        </h2>

        @if ($sections->isNotEmpty())
            <span class="small text-body-secondary">
                {{ $sections->count() }} {{ Str::plural('section', $sections->count()) }}
                · {{ $lessonCount }} {{ Str::plural('lesson', $lessonCount) }}
            </span>
        @endif
    </div>

    @if ($sections->isEmpty())

        <x-ui.empty-state
            icon="bi-collection"
            title="No sections yet"
            class="border-0 rounded-0"
        >
            The course content will appear here once it is added.
        </x-ui.empty-state>

    @else

        <div class="accordion accordion-flush">

            @foreach ($sections as $section)

                @php
                    $collapseId = 'course-section-' . $section->id;
                    $sectionMinutes = $section->lessons->sum($lessonMinutes);
                @endphp

                <div class="accordion-item">

                    <h3 class="accordion-header">
                        <button
                            @class(['accordion-button px-4 gap-3', 'collapsed' => ! $loop->first])
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $collapseId }}"
                            aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                            aria-controls="{{ $collapseId }}"
                        >
                            <span class="flex-grow-1 fw-semibold">
                                Section {{ $loop->iteration }}: {{ $section->title }}
                            </span>

                            <span class="small text-body-secondary text-nowrap">
                                {{ $section->lessons->count() }} {{ Str::plural('lesson', $section->lessons->count()) }}
                                @if ($sectionMinutes)
                                    · {{ $formatMinutes($sectionMinutes) }}
                                @endif
                            </span>
                        </button>
                    </h3>

                    <div id="{{ $collapseId }}" @class(['accordion-collapse collapse', 'show' => $loop->first])>
                        <ul class="list-group list-group-flush">

                            @forelse ($section->lessons as $lesson)

                                @php
                                    $isOpen = $canOpen($lesson);
                                    $isLink = $isOpen && $lessonUrl;
                                @endphp

                                <li @class([
                                    'list-group-item px-4 d-flex align-items-center gap-2',
                                    'list-group-item-action position-relative' => $isLink,
                                ])>

                                    @if ($isOpen)
                                        <i class="bi bi-play-circle text-accent" aria-hidden="true"></i>
                                    @else
                                        <i class="bi bi-lock text-body-secondary" aria-hidden="true"></i>
                                    @endif

                                    <span class="flex-grow-1">
                                        @if ($isLink)
                                            <a href="{{ $lessonUrl($lesson) }}" class="stretched-link text-reset text-decoration-none">
                                                {{ $lesson->title }}
                                            </a>
                                        @else
                                            {{ $lesson->title }}
                                        @endif

                                        @unless ($isEnrolled)
                                            <span class="visually-hidden">
                                                {{ $lesson->is_free_preview ? '(free preview)' : '(locked)' }}
                                            </span>
                                        @endunless
                                    </span>

                                    @if ($lesson->is_free_preview && ! $isEnrolled)
                                        <span class="tag-badge">Preview</span>
                                    @endif

                                    @if ($lessonMinutes($lesson))
                                        <span class="small text-body-secondary text-nowrap">
                                            {{ $lessonMinutes($lesson) }} min{{ $lesson->type === 'article' ? ' read' : '' }}
                                        </span>
                                    @endif

                                </li>

                            @empty

                                <li class="list-group-item px-4 text-body-secondary">
                                    No lessons in this section yet.
                                </li>

                            @endforelse

                        </ul>
                    </div>

                </div>

            @endforeach

        </div>

    @endif

</div>
