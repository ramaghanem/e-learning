{{--
    Statistic card for dashboards and the profile page.

    Usage:
        <x-ui.stat-card icon="bi-journal-bookmark" label="Enrolled courses" :value="$enrolledCount" />
        <x-ui.stat-card icon="bi-award" label="Certificates" :value="0" tone="amber" hint="Optional feature" />

    tone: teal (default), amber, blue, rose
--}}
@props(['icon', 'label', 'value' => 0, 'tone' => 'teal', 'hint' => null])

<div {{ $attributes->class(['stat-card', 'h-100']) }}>
    <span class="stat-icon stat-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
    <div>
        <div class="stat-value">{{ $value }}</div>
        <div class="stat-label">{{ $label }}</div>
        @if ($hint)
            <div class="stat-hint">{{ $hint }}</div>
        @endif
    </div>
</div>
