<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complaint Management System</title>
    <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">
</head>
<body>
    <header class="site-header">
        <a href="{{ route('home') }}" class="site-brand"><x-icon name="shield" /> Complaint Management System</a>
        <nav aria-label="Main navigation" class="site-nav">
            @if ($user)
                <a href="{{ route('dashboard') }}"><x-icon name="layout-dashboard" /> Dashboard</a>
                <a href="{{ route('profile.edit') }}"><x-icon name="user" /> Profile</a>
                <a href="{{ route('complaints.create') }}"><x-icon name="send" /> Raise complaint</a>
                <a href="{{ route('complaints.index') }}"><x-icon name="list" /> My complaints</a>
                @if ($user->isAdmin())
                    <a href="{{ route('admin.dashboard') }}"><x-icon name="shield" /> Admin dashboard</a>
                @endif
                <x-notification-bell />
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><x-icon name="log-out" /> Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}"><x-icon name="log-in" /> Log in</a>
                <a href="{{ route('register') }}"><x-icon name="user-plus" /> Register</a>
            @endif
        </nav>
    </header>
    <main class="page-shell">
        <section class="home-hero">
            <p class="eyebrow">Complaint Management System</p>
            @if ($user)
                <h1>Welcome back</h1>
                <p>Pick up where you left off — check your complaint status or raise a new one.</p>
                <div class="home-hero-actions">
                    <a class="button-link" href="{{ route('dashboard') }}"><x-icon name="layout-dashboard" /> Go to dashboard</a>
                    <a class="button-link button-link--outline" href="{{ route('complaints.create') }}"><x-icon name="send" /> Raise a complaint</a>
                </div>
            @else
                <h1>Raise, track, and resolve complaints in one place</h1>
                <p>Submit a complaint, follow its progress, and get resolution updates — all from a single account.</p>
                <div class="home-hero-actions">
                    <a class="button-link" href="{{ route('register') }}"><x-icon name="user-plus" /> Create an account</a>
                    <a class="button-link button-link--outline" href="{{ route('login') }}"><x-icon name="log-in" /> Log in</a>
                </div>
            @endif
        </section>

        <section class="home-feature-grid" aria-label="How it works">
            <article class="content-card">
                <h3><x-icon name="send" /> 1. Submit</h3>
                <p>Describe the issue, pick a reason, and attach supporting documents if needed.</p>
            </article>
            <article class="content-card">
                <h3><x-icon name="list" /> 2. Track</h3>
                <p>Follow the status of your complaint from pending through to resolution.</p>
            </article>
            <article class="content-card">
                <h3><x-icon name="check-circle" /> 3. Resolve</h3>
                <p>Get updates as your complaint is assigned, reviewed, and closed out.</p>
            </article>
        </section>

        <section class="content-card home-policy-card">
            <h2>Policy guidance</h2>
            <p>Review the relevant policy information before raising a complaint.</p>
            <ul>
                <li>Provide clear, accurate information about the issue.</li>
                <li>Use the complaint reason that best describes your concern.</li>
                <li>Attach supporting documents only when they are relevant and safe to share.</li>
            </ul>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/lib/lucide.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}?v={{ filemtime(public_path('assets/js/main.js')) }}" defer></script>
</body>
</html>
