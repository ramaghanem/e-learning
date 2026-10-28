@php
    $sections = $course->sections ?? collect();
    $lessonLink = $lessonLink ?? null;
    $currentLesson = $currentLesson ?? null;
    $canOpenLesson = $canOpenLesson ?? null;
@endphp

<div class="panel p-0 overflow-hidden">

    <div class="panel-header">
        <h2 class="panel-title mb-0">
            Sections and Lessons
        </h2>
    </div>

    @if ($sections->isEmpty())

        <x-ui.empty-state
            icon="bi-collection"
            title="No sections yet"
            class="border-0 rounded-0"
        >
            Sections and lessons will appear here once they are added.
        </x-ui.empty-state>

    @else

        <ul class="list-group list-group-flush">

            @foreach ($sections as $section)

                <li class="list-group-item px-4 fw-semibold bg-body-tertiary">
                    Section {{ $loop->iteration }}: {{ $section->title }}
                </li>

                @forelse ($section->lessons as $lesson)

                    @if ($lessonLink && $canOpenLesson && ! $canOpenLesson($lesson))
                        <li class="list-group-item px-4 d-flex align-items-center gap-2 text-body-secondary">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <span>{{ $lesson->title }}</span>
                            <span class="visually-hidden">(locked)</span>
                        </li>
                    @elseif ($lessonLink)
                        @php
                            $isCurrent = $currentLesson && $currentLesson->id === $lesson->id;
                        @endphp

                        <li @class([
                            'list-group-item list-group-item-action px-4 d-flex align-items-center gap-2 position-relative',
                            'fw-semibold' => $isCurrent,
                        ])>
                            <i @class([
                                'bi text-accent',
                                'bi-file-earmark-text' => $lesson->type === 'article',
                                'bi-patch-question' => $lesson->type === 'quiz',
                                'bi-play-circle' => ! in_array($lesson->type, ['article', 'quiz']),
                            ])></i>

                            <a
                                href="{{ $lessonLink($lesson) }}"
                                @class(['stretched-link text-decoration-none', 'text-reset' => ! $isCurrent])
                                @if ($isCurrent) aria-current="page" @endif
                            >
                                {{ $lesson->title }}
                            </a>
                        </li>
                    @else
                        <li class="list-group-item px-4 d-flex align-items-center gap-2">
                            <i class="bi bi-play-circle text-accent"></i>
                            <span>{{ $lesson->title }}</span>
                        </li>
                    @endif

                @empty

                    <li class="list-group-item px-4 text-body-secondary">
                        No lessons in this section yet.
                    </li>

                @endforelse

            @endforeach

        </ul>

    @endif

</div>
