<footer class="site-footer mt-auto">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-5">
                <p class="mb-0 footer-text">
                    LearnHub is an online learning platform where students can learn,
                    practice, and track their progress.
                </p>
            </div>

            <div class="col-6 col-lg-3 offset-lg-1">
                <h2 class="footer-heading">Explore</h2>

                <ul class="list-unstyled footer-links">
                    <li>
                        <a href="{{ url('/') }}">Home</a>
                    </li>
                    <li>
                        <a href="#">Courses</a>
                    </li>
                </ul>
            </div>

            <div class="col-6 col-lg-3">
                <h2 class="footer-heading">Account</h2>

                <ul class="list-unstyled footer-links">
                    @guest
                        <li>
                            <a href="{{ route('login') }}">Login</a>
                        </li>
                        <li>
                            <a href="{{ route('register') }}">Register</a>
                        </li>
                    @endguest

                    @auth
                        <li>
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="footer-link-button">
                                    Logout
                                </button>
                            </form>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>

        <hr class="footer-divider my-4">

        <p class="mb-0 small footer-text">
            &copy; {{ date('Y') }} LearnHub. All rights reserved.
        </p>
    </div>
</footer>
