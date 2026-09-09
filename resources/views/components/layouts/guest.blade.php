<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Complaint Management System' }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">
</head>
<body>
    <main class="auth-page">
        <div class="auth-card">
            <a href="{{ route('home') }}" class="site-brand"><x-icon name="shield" /> Complaint Management System</a>
            {{ $slot }}
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/lib/lucide.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}?v={{ filemtime(public_path('assets/js/main.js')) }}" defer></script>
</body>
</html>
