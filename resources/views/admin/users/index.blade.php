<x-layouts.admin title="Users">
    <section class="page-heading">
        <p class="eyebrow">Administration</p>
        <h1>Users</h1>
        <p>Review accounts and manage their status.</p>
    </section>
    <section class="content-card filter-card">
        <form method="get" class="admin-filter-form">
            <input type="search" name="search" maxlength="100" placeholder="Search name or email" value="{{ $filters['search'] }}">
            <select name="status">
                <option value="">All statuses</option>
                @foreach (\App\Models\User::STATUSES as $option)
                    <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <select name="role_id">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($filters['roleId'] === $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <select name="sort">
                <option value="created_at">Created</option>
                <option value="name" @selected($filters['sort'] === 'name')>Name</option>
                <option value="email" @selected($filters['sort'] === 'email')>Email</option>
                <option value="status" @selected($filters['sort'] === 'status')>Status</option>
            </select>
            <select name="order">
                <option value="desc" @selected($filters['order'] === 'desc')>Newest/Z-A</option>
                <option value="asc" @selected($filters['order'] === 'asc')>Oldest/A-Z</option>
            </select>
            <button type="submit"><x-icon name="filter" /> Filter</button>
            <a href="{{ route('admin.users.index') }}"><x-icon name="x" /> Clear</a>
        </form>
    </section>
    <p class="list-meta">{{ $users->total() }} users, page {{ $users->currentPage() }} of {{ $users->lastPage() }}</p>
    <form method="post" action="{{ route('admin.users.bulkStatus') }}" data-confirm="Update the selected users?" data-ajax="true" class="bulk-form">
        @csrf
        <div class="bulk-toolbar">
            <label><input type="checkbox" data-select-all> Select all</label>
            <span data-selected-count>0 selected</span>
            <select name="status" required>
                <option value="">Bulk status</option>
                <option value="approved">Approve</option>
                <option value="blocked">Block</option>
            </select>
            <button type="submit"><x-icon name="check-circle" /> Apply</button>
        </div>
        <section class="content-card table-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th scope="col"></th><th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created</th>
                            <th scope="col">Updated</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" data-row-select></td>
                                <td>#{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->role->name }}</td>
                                <td><x-status-badge :status="$user->status" /></td>
                                <td>{{ $user->created_at }}</td>
                                <td>{{ $user->updated_at }}</td>
                                <td><a href="{{ route('admin.users.show', $user) }}"><x-icon name="eye" /> View details</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </form>
    <x-pagination-links :paginator="$users" />
</x-layouts.admin>
