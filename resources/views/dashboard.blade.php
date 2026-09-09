<x-layouts.app title="Dashboard | Complaint Management System">
    <section class="page-heading">
        <p class="eyebrow">Overview</p>
        <h1>Welcome, {{ $profile->name }}</h1>
        <p>Your account is ready for the next stage of the complaint system.</p>
    </section>
    <section class="dashboard-grid" aria-label="Account summary">
        <article class="content-card dashboard-card">
            <span class="dashboard-label"><x-icon name="user" /> Account status</span>
            <strong><x-status-badge :status="$profile->status" /></strong>
        </article>
        <article class="content-card dashboard-card">
            <span class="dashboard-label"><x-icon name="shield" /> Role</span>
            <strong>{{ $profile->role->name }}</strong>
        </article>
        <article class="content-card dashboard-card">
            <span class="dashboard-label"><x-icon name="calendar" /> Registered</span>
            <strong>{{ $profile->created_at }}</strong>
        </article>
    </section>
    <section class="admin-section">
        <h2>My complaint summary</h2>
        <div class="dashboard-grid">
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="message-square" /> Total complaints</span><strong>{{ $counts['total'] }}</strong></article>
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="clock" /> Pending</span><strong>{{ $counts['pending'] }}</strong></article>
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="refresh-cw" /> In progress</span><strong>{{ $counts['in_progress'] }}</strong></article>
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="check-circle" /> Resolved</span><strong>{{ $counts['resolved'] }}</strong></article>
        </div>
    </section>
    <section class="content-card dashboard-section">
        <h2>Account details</h2>
        <p><strong>Email:</strong> {{ $profile->email }}</p>
        <p>Review your submitted complaints and their current status.</p>
        <a class="button-link" href="{{ route('complaints.index') }}"><x-icon name="list" /> View my complaints</a>
        <a class="button-link" href="{{ route('complaints.create') }}"><x-icon name="send" /> Raise complaint</a>
        <a class="button-link" href="{{ route('profile.edit') }}"><x-icon name="user" /> Manage profile</a>
    </section>
</x-layouts.app>
