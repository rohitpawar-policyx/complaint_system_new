<x-layouts.admin :title="$role === null ? 'Create role' : 'Edit role'">
    <section class="page-heading">
        <p class="eyebrow">Administration</p>
        <h1>{{ $role === null ? 'Create role' : 'Edit role' }}</h1>
        <p><a href="{{ route('admin.roles.index') }}"><x-icon name="arrow-left" /> Back to roles</a></p>
    </section>
    <section class="content-card admin-form-card">
        <form method="post" action="{{ $role === null ? route('admin.roles.store') : route('admin.roles.update', $role) }}" class="admin-form">
            @csrf
            @if ($role !== null) @method('PATCH') @endif
            <label for="name">Name</label>
            <input id="name" name="name" type="text" maxlength="50" value="{{ old('name', $role->name ?? '') }}" required>
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5" maxlength="255">{{ old('description', $role->description ?? '') }}</textarea>
            <button type="submit">
                @if ($role === null)
                    <x-icon name="plus" /> Create role
                @else
                    <x-icon name="save" /> Save changes
                @endif
            </button>
        </form>
    </section>
</x-layouts.admin>
