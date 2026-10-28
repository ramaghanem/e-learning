@php
    $hours = (int) ($course->duration_hours ?? 0);
    $minutes = (int) ($course->duration_minutes ?? 0);
    $duration = trim(($hours ? $hours . 'h ' : '') . ($minutes ? $minutes . 'm' : ''));
@endphp

<ul class="list-unstyled profile-facts text-start mb-0">

    <li>
        <i class="bi bi-tag"></i>
        <div>
            <span class="fact-label">Category</span>
            <span class="fact-value">{{ $course->category?->name ?? '—' }}</span>
        </div>
    </li>

    <li>
        <i class="bi bi-person"></i>
        <div>
            <span class="fact-label">Instructor</span>
            <span class="fact-value">{{ $course->instructor?->name ?? '—' }}</span>
        </div>
    </li>

    <li>
        <i class="bi bi-bar-chart"></i>
        <div>
            <span class="fact-label">Difficulty</span>
            <span class="fact-value">{{ ucfirst($course->difficulty) }}</span>
        </div>
    </li>

    <li>
        <i class="bi bi-translate"></i>
        <div>
            <span class="fact-label">Language</span>
            <span class="fact-value">{{ $course->language ?? '—' }}</span>
        </div>
    </li>

    <li>
        <i class="bi bi-clock"></i>
        <div>
            <span class="fact-label">Duration</span>
            <span class="fact-value">{{ $duration ?: '—' }}</span>
        </div>
    </li>

    <li>
        <i class="bi bi-cash-coin"></i>
        <div>
            <span class="fact-label">Price</span>
            <span class="fact-value">
                {{ $course->price > 0 ? '$' . number_format($course->price, 2) : 'Free' }}
            </span>
        </div>
    </li>

    @if ($showStatus ?? false)
        <li>
            <i class="bi bi-shield-check"></i>
            <div>
                <span class="fact-label">Status</span>
                <span class="fact-value">
                    @include('partials.courses.status-badge', ['course' => $course])
                </span>
            </div>
        </li>
    @endif

</ul>
