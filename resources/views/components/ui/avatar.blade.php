{{--
    User avatar: shows the profile picture if there is one,
    otherwise a circle with the user's initials.

    Usage:
        <x-ui.avatar :user="$user" />
        <x-ui.avatar :user="$user" size="lg" />      sizes: sm, md (default), lg, xl

    Assumes the picture path is saved on the user as "profile_picture"
    (e.g. "profiles/abc123.jpg" inside storage/app/public).
    If you name the column differently later, change it in ONE place: the line below.
--}}
@props(['user', 'size' => 'md'])

@php
    $picture = $user->profile_picture ?? null;

    // "Sara Ahmed" -> "SA"
    $initials = collect(explode(' ', trim($user->name ?? '?')))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

@if ($picture)
    <img src="{{ asset('storage/' . $picture) }}"
         alt="{{ $user->name }}"
         {{ $attributes->class(['avatar', 'avatar-' . $size]) }}>
@else
    <span {{ $attributes->class(['avatar', 'avatar-' . $size, 'avatar-initials']) }}
          role="img" aria-label="{{ $user->name }}">{{ $initials }}</span>
@endif
