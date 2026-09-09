@php $isAdmin = $profile->isAdmin(); @endphp
<x-dynamic-component :component="$isAdmin ? 'layouts.admin' : 'layouts.app'" title="Profile">
    <section class="page-heading">
        <p class="eyebrow">Account</p>
        <h1>Your profile</h1>
        <p>Review your account details and update your contact information.</p>
    </section>
    <div class="profile-layout">
        <section class="content-card">
            <h2>Account information</h2>
            <dl class="account-details">
                <div><dt>Name</dt><dd>{{ $profile->name }}</dd></div>
                <div><dt>Email</dt><dd>{{ $profile->email }}</dd></div>
                <div><dt>Role</dt><dd>{{ $profile->role->name }}</dd></div>
                <div><dt>Status</dt><dd><x-status-badge :status="$profile->status" /></dd></div>
                <div><dt>Registered</dt><dd>{{ $profile->created_at }}</dd></div>
            </dl>
        </section>
        <section class="content-card">
            <h2>Update profile</h2>
            <form method="post" action="{{ route('profile.update') }}" class="profile-form">
                @csrf
                @method('PATCH')
                <label for="name">Name</label>
                <input id="name" name="name" type="text" maxlength="150" value="{{ old('name', $profile->name) }}" required autocomplete="name">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="255" value="{{ old('email', $profile->email) }}" required autocomplete="email">
                <button type="submit"><x-icon name="save" /> Save changes</button>
            </form>
        </section>
    </div>
</x-dynamic-component>
