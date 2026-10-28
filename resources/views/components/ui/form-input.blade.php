{{--
    Reusable form field with label + validation error.

    Usage:
        <x-ui.form-input name="email" label="Email" type="email" required autofocus />

    Props:
        name       the input name (also used as the id)
        label      text shown above the input
        type       text, email, password, ... (default: text)
        value      default value (old() input always wins)
        showError  show the error message under the field (default: true)
        help       optional small hint text under the field
        bag        error bag to read from (default: "default").
                   Breeze's change-password form uses bag="updatePassword".

    Any other attribute (required, autofocus, autocomplete, placeholder, ...) is passed to <input>.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'showError' => true,
    'help' => null,
    'bag' => 'default',
])

@php
    $fieldErrors = $errors->getBag($bag);
    $hasError = $showError && $fieldErrors->has($name);
@endphp

<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>

    <input type="{{ $type }}"
           id="{{ $name }}"
           name="{{ $name }}"
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
           {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}>

    @if ($hasError)
        <div class="invalid-feedback" id="{{ $name }}-error">{{ $fieldErrors->first($name) }}</div>
    @elseif ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
