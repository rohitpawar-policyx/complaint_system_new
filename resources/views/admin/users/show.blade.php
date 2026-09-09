<x-layouts.admin title="User #{{ $user->id }}">
    <section class="page-heading">
        <p class="eyebrow">User management</p>
        <h1>User #{{ $user->id }}</h1>
        <p><a href="{{ route('admin.users.index') }}"><x-icon name="arrow-left" /> Back to users</a></p>
    </section>
    <section class="content-card user-details-card">
        <h2>Account information</h2>
        <dl class="account-details">
            <div><dt>Name</dt><dd>{{ $user->name }}</dd></div>
            <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
            <div><dt>Role</dt><dd>{{ $user->role->name }}</dd></div>
            <div><dt>Status</dt><dd><x-status-badge :status="$user->status" /></dd></div>
            <div><dt>Created</dt><dd>{{ $user->created_at }}</dd></div>
            <div><dt>Updated</dt><dd>{{ $user->updated_at }}</dd></div>
        </dl>
    </section>
    <section class="content-card status-card">
        <h2>Account status</h2>
        @if (auth()->id() === $user->id)
            <p>Your own account status cannot be changed here.</p>
        @else
            <form method="post" action="{{ route('admin.users.status', $user) }}" class="status-form" data-confirm="Update this user's account status?">
                @csrf
                @method('PATCH')
                <label for="status">Set status</label>
                <select id="status" name="status" required>
                    @foreach (\App\Models\User::STATUSES as $option)
                        <option value="{{ $option }}" @selected($user->status === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                <button type="submit"><x-icon name="save" /> Update status</button>
            </form>
        @endif
    </section>
</x-layouts.admin>
