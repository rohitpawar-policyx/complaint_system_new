<x-layouts.guest title="Register | Complaint Management System">
    <h1>Register</h1>
    <p>Create an account. Access is granted after admin approval.</p>

    @if ($errors->any())
        <p class="auth-message auth-message--error">{{ $errors->first() }}</p>
    @endif

    <form method="post" action="{{ route('register') }}" class="auth-form">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        <button type="submit"><x-icon name="user-plus" /> Create account</button>
    </form>

    <p>Already registered? <a href="{{ route('login') }}">Log in</a></p>
</x-layouts.guest>
