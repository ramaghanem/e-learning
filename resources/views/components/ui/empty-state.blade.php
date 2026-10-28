{{--
    Friendly "nothing here yet" box.

    Usage:
        <x-ui.empty-state icon="bi-journal-x" title="No courses yet">
            You haven't enrolled in any courses yet.
            <x-slot:action>
                <a href="#" class="btn btn-primary">Browse Courses</a>
            </x-slot:action>
        </x-ui.empty-state>
--}}
@props(['icon' => 'bi-inbox', 'title'])

<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state-icon"><i class="bi {{ $icon }}"></i></span>
    <h3 class="empty-state-title">{{ $title }}</h3>
    @if (trim($slot))
        <p class="text-body-secondary mb-0">{{ $slot }}</p>
    @endif
    @isset($action)
        <div class="mt-3">{{ $action }}</div>
    @endisset
</div>
