<x-layouts.admin title="Complaint Reasons">
    <section class="page-heading">
        <p class="eyebrow">Administration</p>
        <h1>Complaint reasons</h1>
        <p>Manage the reasons and server-controlled priorities used for new complaints.</p>
    </section>
    <section class="content-card filter-card">
        <form method="get" class="admin-filter-form">
            <input type="search" name="search" maxlength="100" placeholder="Search reason" value="{{ $filters['search'] }}">
            <select name="priority">
                <option value="">All priorities</option>
                @foreach (\App\Models\Complaint::PRIORITIES as $option)
                    <option value="{{ $option }}" @selected($filters['priority'] === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <select name="active">
                <option value="">All states</option>
                <option value="active" @selected($filters['active'] === 'active')>Active</option>
                <option value="inactive" @selected($filters['active'] === 'inactive')>Inactive</option>
            </select>
            <select name="sort">
                <option value="name">Name</option>
                <option value="priority" @selected($filters['sort'] === 'priority')>Priority</option>
                <option value="active" @selected($filters['sort'] === 'active')>Active</option>
                <option value="created_at" @selected($filters['sort'] === 'created_at')>Created</option>
            </select>
            <select name="order">
                <option value="asc">A-Z/Oldest</option>
                <option value="desc" @selected($filters['order'] === 'desc')>Z-A/Newest</option>
            </select>
            <button type="submit"><x-icon name="filter" /> Filter</button>
            <a href="{{ route('admin.reasons.index') }}"><x-icon name="x" /> Clear</a>
        </form>
    </section>
    <p class="list-meta">{{ $reasons->total() }} reasons, page {{ $reasons->currentPage() }} of {{ $reasons->lastPage() }}</p>
    <form method="post" action="{{ route('admin.reasons.bulkActive') }}" data-confirm="Update the selected reasons?" data-ajax="true" class="bulk-form">
        @csrf
        <div class="bulk-toolbar">
            <label><input type="checkbox" data-select-all> Select all</label>
            <span data-selected-count>0 selected</span>
            <select name="active" required>
                <option value="">Bulk state</option>
                <option value="1">Activate</option>
                <option value="0">Deactivate</option>
            </select>
            <button type="submit"><x-icon name="check-circle" /> Apply</button>
        </div>
        <p><a class="button-link" href="{{ route('admin.reasons.create') }}"><x-icon name="plus" /> Create reason</a></p>
        @if ($reasons->isEmpty())
            <section class="content-card empty-state"><h2>No reasons found</h2><p>Create a complaint reason to make it available to users.</p></section>
        @else
            <section class="content-card table-card"><div class="table-wrapper"><table>
                <thead><tr><th></th><th>Name</th><th>Description</th><th>Priority</th><th>Active</th><th>Created</th><th>Updated</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    @foreach ($reasons as $reason)
                        <tr>
                            <td><input type="checkbox" name="reason_ids[]" value="{{ $reason->id }}" data-row-select></td>
                            <td>{{ $reason->name }}</td>
                            <td>{{ $reason->description }}</td>
                            <td><x-priority-badge :priority="$reason->priority" /></td>
                            <td><x-status-badge :status="$reason->active ? 'active' : 'inactive'" /></td>
                            <td>{{ $reason->created_at }}</td>
                            <td>{{ $reason->updated_at }}</td>
                            <td class="table-actions">
                                <a href="{{ route('admin.reasons.edit', $reason) }}"><x-icon name="pencil" /> Edit</a>
                                <button type="submit" form="reason-delete-{{ $reason->id }}"><x-icon name="trash-2" /> Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div></section>
        @endif
    </form>
    @foreach ($reasons as $reason)
        <form id="reason-delete-{{ $reason->id }}" method="post" action="{{ route('admin.reasons.destroy', $reason) }}" data-confirm="Delete this complaint reason if it is not used by complaints?" class="visually-hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
    <x-pagination-links :paginator="$reasons" />
</x-layouts.admin>
