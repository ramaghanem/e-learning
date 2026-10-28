@extends('layouts.main')

@section('title', 'Home')

@section('content')

    {{-- Hero --}}
    <section class="hero">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">

                    <span class="eyebrow">
                        <i class="bi bi-stars"></i>
                        Online Learning Platform
                    </span>

                    <h1 class="hero-title">
                        Learn. Practice. Grow.
                    </h1>

                    <p class="hero-lead">
                        LearnHub is an online learning platform where students can
                        learn new skills, complete courses, and track their progress.
                    </p>

                    <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-4">
                        Get Started
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>

                </div>
            </div>
        </div>
    </section>


    {{-- Features --}}
    <section class="section">
        <div class="container">

            <div class="section-head text-center">
                <span class="eyebrow">LearnHub</span>
                <h2 class="section-title">What you can do</h2>
            </div>

            <div class="row g-4">

                <div class="col-md-4">
                    <x-ui.feature-card
                        icon="bi-play-circle"
                        title="Learn"
                    >
                        Follow courses and learn through organized lessons.
                    </x-ui.feature-card>
                </div>

                <div class="col-md-4">
                    <x-ui.feature-card
                        icon="bi-patch-question"
                        title="Practice"
                    >
                        Take quizzes to test your understanding.
                    </x-ui.feature-card>
                </div>

                <div class="col-md-4">
                    <x-ui.feature-card
                        icon="bi-graph-up-arrow"
                        title="Track Progress"
                    >
                        Keep track of your progress as you complete your courses.
                    </x-ui.feature-card>
                </div>

            </div>
        </div>
    </section>


    {{-- How it works --}}
    <section class="section section-tinted">
        <div class="container">

            <div class="section-head text-center">
                <span class="eyebrow">How it works</span>
                <h2 class="section-title">Start learning in three steps</h2>
            </div>

            <div class="row g-4">

                <div class="col-md-4">
                    <div class="step-card h-100">
                        <span class="step-number">1</span>
                        <i class="bi bi-person-plus step-icon"></i>

                        <h3 class="feature-title">
                            Create an account
                        </h3>

                        <p class="mb-0 text-body-secondary">
                            Sign up and create your LearnHub account.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="step-card h-100">
                        <span class="step-number">2</span>
                        <i class="bi bi-journal-bookmark step-icon"></i>

                        <h3 class="feature-title">
                            Choose a course
                        </h3>

                        <p class="mb-0 text-body-secondary">
                            Browse the available courses and enroll in one.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="step-card h-100">
                        <span class="step-number">3</span>
                        <i class="bi bi-check-circle step-icon"></i>

                        <h3 class="feature-title">
                            Start learning
                        </h3>

                        <p class="mb-0 text-body-secondary">
                            Complete lessons and track your progress.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
