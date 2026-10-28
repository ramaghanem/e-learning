{{--
    Small feature card used on the home page.

    Usage:
        <x-ui.feature-card icon="bi-play-circle" title="Video lessons">
            Short description here.
        </x-ui.feature-card>
--}}
@props(['icon', 'title'])

<div class="feature-card h-100">
    <span class="feature-icon"><i class="bi {{ $icon }}"></i></span>
    <h3 class="feature-title">{{ $title }}</h3>
    <p class="mb-0 text-body-secondary">{{ $slot }}</p>
</div>
