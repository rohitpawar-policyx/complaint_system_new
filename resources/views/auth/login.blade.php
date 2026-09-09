<x-layouts.guest title="Log In | Complaint Management System">
    <h1>Log in</h1>
    <p>Access your complaint management account.</p>

    @if (session('status'))
        <p class="auth-message auth-message--success">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p class="auth-message auth-message--error">{{ $errors->first() }}</p>
    @endif

    <form method="post" action="{{ route('login') }}" class="auth-form">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <label class="checkbox-label"><input type="checkbox" name="remember"> Remember me</label>
        <button type="submit"><x-icon name="log-in" /> Log in</button>
    </form>

    <p>Need an account? <a href="{{ route('register') }}">Register</a></p>
</x-layouts.guest>
