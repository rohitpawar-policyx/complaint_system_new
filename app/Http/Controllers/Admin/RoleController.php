<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);
        $sort = in_array($request->query('sort'), ['name', 'created_at'], true) ? $request->query('sort') : 'name';
        $order = $request->query('order') === 'desc' ? 'desc' : 'asc';

        $query = Role::query();
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->orderBy($sort, $order)->paginate(20)->withQueryString();

        return view('admin.roles.index', ['roles' => $roles, 'filters' => compact('search', 'sort', 'order')]);
    }

    public function create(): View
    {
        return view('admin.roles.form', ['role' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        Role::create($validated);

        return redirect()->route('admin.roles.index')->with('status', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', ['role' => $role]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $role->update($validated);

        return redirect()->route('admin.roles.index')->with('status', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            $role->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return back()->withErrors(['role' => 'This role is currently in use and cannot be deleted.']);
        }

        return back()->with('status', 'Role deleted successfully.');
    }
}
