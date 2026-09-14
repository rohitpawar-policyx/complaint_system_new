<x-layouts.app title="Dashboard | Complaint Management System">
    <section class="page-heading">
        <p class="eyebrow">Welcome back</p>
        <h1>Hi, {{ $profile->name }}</h1>
        <p>Your account is <x-status-badge :status="$profile->status" /> — here's what you can do next.</p>
    </section>
    <section class="content-card">
        <h2>Quick actions</h2>
        <div class="home-hero-actions">
            <a class="button-link" href="{{ route('complaints.create') }}"><x-icon name="send" /> Raise a complaint</a>
            <a class="button-link button-link--outline" href="{{ route('complaints.index') }}"><x-icon name="list" /> View my complaints</a>
            <a class="button-link button-link--outline" href="{{ route('profile.edit') }}"><x-icon name="user" /> Manage profile</a>
        </div>
    </section>
</x-layouts.app>
