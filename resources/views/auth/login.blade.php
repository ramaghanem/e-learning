{{-- <x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout> --}}

@extends('layouts.main')

@section('title', 'Login')

@section('content')
    <x-ui.auth-card title="Welcome back" subtitle="Log in to continue learning.">

        {{-- Status message from Breeze (e.g. after a password reset) --}}
        @if (session('status'))
            <div class="alert alert-success" role="alert">{{ session('status') }}</div>
        @endif


        @if ($errors->any())
            <div class="alert alert-danger d-flex gap-2" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div>
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <x-ui.form-input name="email" label="Email" type="email" :show-error="false"
                             required autofocus autocomplete="username" placeholder="you@example.com" />

            <x-ui.form-input name="password" label="Password" type="password" :show-error="false"
                             required autocomplete="current-password" />

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    {{-- Breeze reads this as $request->boolean('remember') --}}
                    <input class="form-check-input" type="checkbox" name="remember" id="remember_me"
                           {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember_me">Remember me</label>
                </div>

                
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
        </form>

        <x-slot:footer>
            Don't have an account?
            <a href="{{ route('register') }}" class="fw-semibold">Register</a>
        </x-slot:footer>

    </x-ui.auth-card>
@endsection
