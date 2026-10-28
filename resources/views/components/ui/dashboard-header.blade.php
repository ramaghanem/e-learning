{{--
    Welcome banner at the top of each dashboard.

    Usage:
        <x-ui.dashboard-header eyebrow="Student dashboard" title="Welcome back, Sara!" subtitle="...">
            <x-slot:action> <a class="btn btn-light" href="#">Browse Courses</a> </x-slot:action>
        </x-ui.dashboard-header>
--}}
@props(['eyebrow', 'title', 'subtitle' => null])

<div class="dashboard-header">
    <div>
        <span class="eyebrow eyebrow-light">{{ $eyebrow }}</span>
        <h1 class="dashboard-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($action)
        <div class="d-flex flex-wrap gap-2">{{ $action }}</div>
    @endisset
</div>
