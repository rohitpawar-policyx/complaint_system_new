<x-layouts.admin title="Roles">
    <section class="page-heading">
        <p class="eyebrow">Administration</p>
        <h1>Roles</h1>
        <p>Manage the role definitions used by the system.</p>
    </section>
    <section class="content-card filter-card">
        <form method="get" class="admin-filter-form">
            <input type="search" name="search" maxlength="100" placeholder="Search role name" value="{{ $filters['search'] }}">
            <select name="sort">
                <option value="name">Name</option>
                <option value="created_at" @selected($filters['sort'] === 'created_at')>Created</option>
            </select>
            <select name="order">
                <option value="asc">A-Z/Oldest</option>
                <option value="desc" @selected($filters['order'] === 'desc')>Z-A/Newest</option>
            </select>
            <button type="submit"><x-icon name="filter" /> Filter</button>
            <a href="{{ route('admin.roles.index') }}"><x-icon name="x" /> Clear</a>
        </form>
    </section>
    <p class="list-meta">{{ $roles->total() }} roles, page {{ $roles->currentPage() }} of {{ $roles->lastPage() }}</p>
    <p><a class="button-link" href="{{ route('admin.roles.create') }}"><x-icon name="plus" /> Create role</a></p>
    @if ($roles->isEmpty())
        <section class="content-card empty-state"><h2>No roles found</h2><p>Create a role to make it available for future administration.</p></section>
    @else
        <section class="content-card table-card">
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Name</th><th>Description</th><th>Created</th><th>Updated</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td>{{ $role->name }}</td>
                                <td>{{ $role->description }}</td>
                                <td>{{ $role->created_at }}</td>
                                <td>{{ $role->updated_at }}</td>
                                <td class="table-actions">
                                    <a href="{{ route('admin.roles.edit', $role) }}"><x-icon name="pencil" /> Edit</a>
                                    <form method="post" action="{{ route('admin.roles.destroy', $role) }}" data-confirm="Delete this role if it is not protected or in use?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"><x-icon name="trash-2" /> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
    <x-pagination-links :paginator="$roles" />
</x-layouts.admin>
