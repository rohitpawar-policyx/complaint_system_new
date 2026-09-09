<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin' }} | Complaint Management System</title>
    <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">
</head>
<body>
    @php
        $navGroups = [
            ['label' => 'Admin', 'items' => [
                ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
            ]],
            ['label' => 'Management', 'items' => [
                ['route' => 'admin.users.index', 'label' => 'Users', 'icon' => 'users'],
                ['route' => 'admin.roles.index', 'label' => 'Roles', 'icon' => 'shield'],
                ['route' => 'admin.reasons.index', 'label' => 'Complaint Reasons', 'icon' => 'tags'],
            ]],
            ['label' => 'Complaints', 'items' => [
                ['route' => 'admin.complaints.index', 'label' => 'Complaints', 'icon' => 'message-square'],
                ['route' => 'admin.history.index', 'label' => 'Complaint History', 'icon' => 'history'],
            ]],
        ];
    @endphp
    <div class="admin-shell" data-admin-shell>
        <div class="admin-sidebar-overlay" data-sidebar-overlay hidden></div>
        <aside class="admin-sidebar" id="admin-sidebar" data-sidebar aria-label="Admin navigation">
            <div class="admin-sidebar-brand">
                <a href="{{ route('admin.dashboard') }}" class="site-brand"><x-icon name="shield" /> Complaint Management System</a>
                <button type="button" class="admin-sidebar-close" data-sidebar-close aria-label="Close navigation menu"><x-icon name="x" /></button>
            </div>
            <nav aria-label="Admin sections">
                @foreach($navGroups as $group)
                    <p class="admin-nav-group-title">{{ $group['label'] }}</p>
                    <ul class="admin-nav-list">
                        @foreach($group['items'] as $item)
                            @php $isActive = request()->routeIs($item['route'] . '*'); @endphp
                            <li>
                                <a href="{{ route($item['route']) }}" class="admin-nav-link{{ $isActive ? ' is-active' : '' }}" @if($isActive) aria-current="page" @endif title="{{ $item['label'] }}">
                                    <x-icon :name="$item['icon']" /><span class="admin-nav-label">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>
        </aside>
        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar-left">
                    <button type="button" class="admin-sidebar-toggle" data-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="Toggle navigation menu">
                        <x-icon name="menu" />
                    </button>
                    <h1 class="admin-topbar-title">{{ $title ?? 'Admin' }}</h1>
                </div>
                <div class="admin-topbar-right">
                    <x-notification-bell />
                    <a href="{{ route('profile.edit') }}" class="admin-topbar-user" title="View profile">
                        <x-icon name="user-round" />
                        <span class="admin-topbar-user-text">
                            <strong>{{ auth()->user()->name }}</strong>
                            <span>Administrator</span>
                        </span>
                    </a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="admin-topbar-logout"><x-icon name="log-out" /> Log out</button>
                    </form>
                </div>
            </header>
            <main class="admin-content page-shell">
                @if (session('status'))
                    <p class="auth-message auth-message--success">{{ session('status') }}</p>
                @endif
                @if ($errors->any())
                    <p class="auth-message auth-message--error">{{ $errors->first() }}</p>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/lib/lucide.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}?v={{ filemtime(public_path('assets/js/main.js')) }}" defer></script>
</body>
</html>
