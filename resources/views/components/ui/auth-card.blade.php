{{--
    Card used by the Login and Register pages.

    Usage:
        <x-ui.auth-card title="Welcome back" subtitle="Log in to continue.">
            ...form...
            <x-slot:footer> link to the other page </x-slot:footer>
        </x-ui.auth-card>
--}}
@props(['title', 'subtitle' => null])

<section class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-9 col-xl-8">
                <div class="card auth-card border-0 overflow-hidden">
                    <div class="row g-0">

                        {{-- Left side: only visible on large screens --}}
                        <div class="col-lg-5 d-none d-lg-flex auth-aside">
                            <div class="d-flex flex-column justify-content-between p-4 p-xl-5 w-100">
                                <span class="brand-mark brand-mark-light"><i class="bi bi-mortarboard-fill"></i></span>
                                <div>
                                    <p class="auth-aside-title">Learn at your own pace.</p>
                                    <ul class="list-unstyled auth-aside-list mb-0">
                                        <li><i class="bi bi-play-circle"></i> Video &amp; article lessons</li>
                                        <li><i class="bi bi-patch-question"></i> Quizzes to test yourself</li>
                                        <li><i class="bi bi-graph-up-arrow"></i> Progress you can see</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- Right side: the form --}}
                        <div class="col-lg-7">
                            <div class="p-4 p-sm-5">
                                <h1 class="auth-title">{{ $title }}</h1>
                                @if ($subtitle)
                                    <p class="text-body-secondary mb-4">{{ $subtitle }}</p>
                                @endif

                                {{ $slot }}

                                @isset($footer)
                                    <p class="text-center text-body-secondary mt-4 mb-0">
                                        {{ $footer }}
                                    </p>
                                @endisset
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
