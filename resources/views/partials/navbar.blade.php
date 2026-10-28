
<nav class="navbar navbar-expand-lg site-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
            <span>LearnHub</span>
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar"
            aria-controls="mainNavbar"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto ms-lg-4">
                <li class="nav-item">
                    <a
                        class="nav-link {{ request()->is('/') ? 'active' : '' }}"
                        href="{{ url('/') }}"
                    >
                        Home
                    </a>
                </li>

                <li class="nav-item">
                    @auth
                        @if (auth()->user()->role === 'admin')
                            <a
                                class="nav-link {{ request()->routeIs('admin.courses.*') ? 'active' : '' }}"
                                href="{{ route('admin.courses.index') }}"
                            >
                                Courses
                            </a>
                        @elseif (auth()->user()->role === 'instructor')
                            <a
                                class="nav-link {{ request()->routeIs('instructor.courses.*') ? 'active' : '' }}"
                                href="{{ route('instructor.courses.index') }}"
                            >
                                Courses
                            </a>
                        @else
                            <a
                                class="nav-link"
                                href="{{ url('/courses') }}"
                            >
                                Courses
                            </a>
                        @endif
                    @else
                        <a class="nav-link" href="{{ url('/courses') }}">
                            Courses
                        </a>
                    @endauth
                </li>
            </ul>

            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 pt-3 pt-lg-0">

                @guest
                    <a
                        class="btn btn-outline-primary {{ request()->routeIs('login') ? 'active' : '' }}"
                        href="{{ route('login') }}"
                    >
                        Login
                    </a>

                    <a
                        class="btn btn-primary {{ request()->routeIs('register') ? 'active' : '' }}"
                        href="{{ route('register') }}"
                    >
                        Register
                    </a>
                @endguest

                @auth
                    <div class="dropdown me-lg-2">
                        <button
                            class="btn btn-link user-menu-toggle dropdown-toggle d-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            <x-ui.avatar :user="auth()->user()" size="sm" />
                            {{ auth()->user()->name }}
                        </button>

                        <ul class="dropdown-menu dropdown-menu-lg-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('dashboard') }}">
                                    <i class="bi bi-speedometer2 me-2"></i>
                                    Dashboard
                                </a>
                            </li>

                            @if (auth()->user()->role === 'student')
                                <li>
                                    <a class="dropdown-item" href="{{ route('student.courses.index') }}">
                                        <i class="bi bi-journal-bookmark me-2"></i>
                                        My Courses
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item" href="{{ route('student.statistics') }}">
                                        <i class="bi bi-bar-chart me-2"></i>
                                        My Statistics
                                    </a>
                                </li>
                            @endif

                            <li>
                                <a class="dropdown-item" href="{{ route('profile.show') }}">
                                    <i class="bi bi-person me-2"></i>
                                    My Profile
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    <i class="bi bi-gear me-2"></i>
                                    Edit Profile
                                </a>
                            </li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf

                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-box-arrow-right me-1"></i>
                            Logout
                        </button>
                    </form>
                @endauth

            </div>
        </div>
    </div>
</nav>
