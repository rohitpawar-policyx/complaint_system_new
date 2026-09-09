<x-layouts.admin title="Complaint management">
    <section class="page-heading">
        <p class="eyebrow">Administration</p>
        <h1>Complaint management</h1>
        <p>Review complaints, assignments, status, and history.</p>
    </section>
    <section class="content-card filter-card">
        <form method="get" class="admin-filter-form">
            <select name="status">
                <option value="">All statuses</option>
                @foreach (\App\Models\Complaint::STATUSES as $option)
                    <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <select name="priority">
                <option value="">All priorities</option>
                @foreach (\App\Models\Complaint::PRIORITIES as $option)
                    <option value="{{ $option }}" @selected($filters['priority'] === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <select name="reason_id">
                <option value="">All reasons</option>
                @foreach ($reasons as $reason)
                    <option value="{{ $reason->id }}" @selected($filters['reasonId'] === $reason->id)>{{ $reason->name }}</option>
                @endforeach
            </select>
            <select name="assignee_id">
                <option value="">All assignees</option>
                @foreach ($assignees as $assignee)
                    <option value="{{ $assignee->id }}" @selected($filters['assigneeId'] === $assignee->id)>{{ $assignee->name }}</option>
                @endforeach
            </select>
            <select name="sort">
                <option value="created_at">Created</option>
                <option value="id" @selected($filters['sort'] === 'id')>ID</option>
                <option value="priority" @selected($filters['sort'] === 'priority')>Priority</option>
                <option value="status" @selected($filters['sort'] === 'status')>Status</option>
                <option value="updated_at" @selected($filters['sort'] === 'updated_at')>Updated</option>
            </select>
            <select name="order">
                <option value="desc">Newest/highest</option>
                <option value="asc" @selected($filters['order'] === 'asc')>Oldest/lowest</option>
            </select>
            <input type="search" name="search" maxlength="100" value="{{ $filters['search'] }}" placeholder="Search ID, name, email, reason">
            <button type="submit"><x-icon name="filter" /> Filter</button>
            <a href="{{ route('admin.complaints.index') }}"><x-icon name="x" /> Clear</a>
        </form>
    </section>
    <p class="list-meta">{{ $complaints->total() }} complaints, page {{ $complaints->currentPage() }} of {{ $complaints->lastPage() }}</p>
    @if ($complaints->isEmpty())
        <section class="content-card empty-state"><h2>No complaints found</h2><p>Try changing the filters or wait for a complaint to be submitted.</p></section>
    @else
        <section class="content-card table-card"><div class="table-wrapper"><table>
            <thead><tr><th>ID</th><th>Complainant</th><th>Reason</th><th>Priority</th><th>Status</th><th>Assignee</th><th>Created</th><th>Updated</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
                @foreach ($complaints as $complaint)
                    <tr>
                        <td>#{{ $complaint->id }}</td>
                        <td>{{ $complaint->user->name }}</td>
                        <td>{{ $complaint->reason->name }}</td>
                        <td><x-priority-badge :priority="$complaint->priority" /></td>
                        <td><x-status-badge :status="$complaint->status" /></td>
                        <td>{{ $complaint->assignee->name ?? 'Unassigned' }}</td>
                        <td>{{ $complaint->created_at }}</td>
                        <td>{{ $complaint->updated_at }}</td>
                        <td><a href="{{ route('admin.complaints.show', $complaint) }}"><x-icon name="eye" /> View details</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table></div></section>
    @endif
    <x-pagination-links :paginator="$complaints" />
</x-layouts.admin>
