<x-layouts.admin :title="$reason === null ? 'Create complaint reason' : 'Edit complaint reason'">
    <section class="page-heading">
        <p class="eyebrow">Administration</p>
        <h1>{{ $reason === null ? 'Create complaint reason' : 'Edit complaint reason' }}</h1>
        <p><a href="{{ route('admin.reasons.index') }}"><x-icon name="arrow-left" /> Back to complaint reasons</a></p>
    </section>
    <section class="content-card admin-form-card">
        <form method="post" action="{{ $reason === null ? route('admin.reasons.store') : route('admin.reasons.update', $reason) }}" class="admin-form">
            @csrf
            @if ($reason !== null) @method('PATCH') @endif
            <label for="name">Name</label>
            <input id="name" name="name" type="text" maxlength="150" value="{{ old('name', $reason->name ?? '') }}" required>
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5">{{ old('description', $reason->description ?? '') }}</textarea>
            <label for="priority">Priority</label>
            <select id="priority" name="priority" required>
                @foreach (\App\Models\Complaint::PRIORITIES as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', $reason->priority ?? '') === $priority)>{{ $priority }}</option>
                @endforeach
            </select>
            <label class="checkbox-label"><input type="checkbox" name="active" value="1" @checked($reason === null || $reason->active)> Active for new complaints</label>
            <button type="submit">
                @if ($reason === null)
                    <x-icon name="plus" /> Create reason
                @else
                    <x-icon name="save" /> Save changes
                @endif
            </button>
        </form>
    </section>
</x-layouts.admin>
