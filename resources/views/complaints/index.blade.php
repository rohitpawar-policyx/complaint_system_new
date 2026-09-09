<x-layouts.app title="My Complaints | Complaint Management System">
    <section class="page-heading">
        <p class="eyebrow">Complaints</p>
        <h1>My complaints</h1>
        <p>Review complaints submitted from your account.</p>
    </section>
    <section class="content-card filter-card">
        <form method="get" class="admin-filter-form">
            <select name="status">
                <option value="">All statuses</option>
                @foreach (\App\Models\Complaint::STATUSES as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <select name="priority">
                <option value="">All priorities</option>
                @foreach (\App\Models\Complaint::PRIORITIES as $option)
                    <option value="{{ $option }}" @selected($priority === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <select name="sort">
                <option value="created_at">Created</option>
                <option value="updated_at" @selected($sort === 'updated_at')>Updated</option>
                <option value="status" @selected($sort === 'status')>Status</option>
                <option value="priority" @selected($sort === 'priority')>Priority</option>
            </select>
            <select name="order">
                <option value="desc">Newest/highest</option>
                <option value="asc" @selected($order === 'asc')>Oldest/lowest</option>
            </select>
            <button type="submit"><x-icon name="filter" /> Filter</button>
            <a href="{{ route('complaints.index') }}"><x-icon name="x" /> Clear</a>
        </form>
    </section>
    <p class="list-meta">{{ $complaints->total() }} complaints, page {{ $complaints->currentPage() }} of {{ $complaints->lastPage() }}</p>
    @if ($complaints->isEmpty())
        <section class="content-card empty-state">
            <h2>No complaints yet</h2>
            <p>When you submit a complaint, it will appear here.</p>
            <a class="button-link" href="{{ route('complaints.create') }}"><x-icon name="send" /> Raise a complaint</a>
        </section>
    @else
        <section class="content-card table-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Priority</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created</th>
                            <th scope="col">Updated</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($complaints as $complaint)
                            <tr>
                                <td>#{{ $complaint->id }}</td>
                                <td>{{ $complaint->reason->name }}</td>
                                <td><x-priority-badge :priority="$complaint->priority" /></td>
                                <td><x-status-badge :status="$complaint->status" /></td>
                                <td>{{ $complaint->created_at }}</td>
                                <td>{{ $complaint->updated_at }}</td>
                                <td><a href="{{ route('complaints.show', $complaint) }}"><x-icon name="eye" /> View details</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
    <x-pagination-links :paginator="$complaints" />
</x-layouts.app>
