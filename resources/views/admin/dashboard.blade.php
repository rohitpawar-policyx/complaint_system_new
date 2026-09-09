<x-layouts.admin title="Admin dashboard">
    <section class="page-heading">
        <p class="eyebrow">Administration</p>
        <h1>Admin dashboard</h1>
        <p>Monitor account and complaint activity.</p>
    </section>
    <section class="admin-section">
        <h2>Users</h2>
        <div class="dashboard-grid admin-count-grid">
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="users" /> Total users</span><strong>{{ $counts['users']['total'] }}</strong></article>
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="clock" /> Pending</span><strong>{{ $counts['users']['pending'] }}</strong></article>
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="check-circle" /> Approved</span><strong>{{ $counts['users']['approved'] }}</strong></article>
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="ban" /> Blocked</span><strong>{{ $counts['users']['blocked'] }}</strong></article>
        </div>
    </section>
    <section class="admin-section">
        <h2>Complaints</h2>
        <div class="dashboard-grid admin-count-grid">
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="message-square" /> Total complaints</span><strong>{{ $counts['complaints']['total'] }}</strong></article>
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="clock" /> Pending</span><strong>{{ $counts['complaints']['pending'] }}</strong></article>
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="refresh-cw" /> In progress</span><strong>{{ $counts['complaints']['in_progress'] }}</strong></article>
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="check-circle" /> Resolved</span><strong>{{ $counts['complaints']['resolved'] }}</strong></article>
            <article class="content-card dashboard-card"><span class="dashboard-label"><x-icon name="alert-triangle" /> High priority</span><strong>{{ $counts['complaints']['high_priority'] }}</strong></article>
        </div>
    </section>
</x-layouts.admin>
