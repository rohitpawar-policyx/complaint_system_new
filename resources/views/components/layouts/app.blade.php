<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Complaint Management System' }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">
</head>
<body>
    <header class="site-header">
        <a href="{{ route('dashboard') }}" class="site-brand"><x-icon name="shield" /> Complaint Management System</a>
        <nav aria-label="Main navigation" class="site-nav">
            <a href="{{ route('home') }}"><x-icon name="home" /> Home</a>
            <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard" /> Dashboard</a>
            <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif><x-icon name="user" /> Profile</a>
            <a href="{{ route('complaints.create') }}" @if(request()->routeIs('complaints.create')) aria-current="page" @endif><x-icon name="send" /> Raise complaint</a>
            <a href="{{ route('complaints.index') }}" @if(request()->routeIs('complaints.index') || request()->routeIs('complaints.show')) aria-current="page" @endif><x-icon name="list" /> My complaints</a>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}"><x-icon name="shield" /> Admin dashboard</a>
            @endif
            <x-notification-bell />
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit"><x-icon name="log-out" /> Log out</button>
            </form>
        </nav>
    </header>
    <main class="page-shell">
        @if (session('status'))
            <p class="auth-message auth-message--success">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="auth-message auth-message--error">{{ $errors->first() }}</p>
        @endif
        {{ $slot }}
    </main>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/lib/lucide.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}?v={{ filemtime(public_path('assets/js/main.js')) }}" defer></script>
</body>
</html>
