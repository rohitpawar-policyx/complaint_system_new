<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserApprovedNotification;
use App\Notifications\UserBlockedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), User::STATUSES, true)
            ? $request->query('status') : '';
        $roleId = $request->query('role_id') ? (int) $request->query('role_id') : null;
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);
        $sort = in_array($request->query('sort'), ['name', 'email', 'status', 'created_at'], true)
            ? $request->query('sort') : 'created_at';
        $order = strtolower((string) $request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = User::with('role');
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($roleId !== null) {
            $query->where('role_id', $roleId);
        }
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        $users = $query->orderBy($sort, $order)->paginate(20)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'filters' => compact('status', 'roleId', 'search', 'sort', 'order'),
        ]);
    }

    public function show(User $user): View
    {
        return view('admin.users.show', ['user' => $user->load('role')]);
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,blocked'],
        ]);

        if ($user->id === $request->user()->id && $validated['status'] === 'blocked') {
            return back()->withErrors(['status' => 'You cannot block your own administrator account.']);
        }

        $oldStatus = $user->status;
        $user->update(['status' => $validated['status']]);

        if ($oldStatus !== $validated['status']) {
            $this->notifyStatusChange($user, $validated['status']);
        }

        return back()->with('status', 'User status updated successfully.');
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,blocked'],
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer'],
        ]);

        $adminId = $request->user()->id;
        $targetIds = array_values(array_diff($validated['user_ids'], [$adminId]));

        $changedUsers = User::whereIn('id', $targetIds)->where('status', '!=', $validated['status'])->get();

        DB::transaction(function () use ($changedUsers, $validated) {
            User::whereIn('id', $changedUsers->pluck('id'))->update(['status' => $validated['status']]);
        });

        foreach ($changedUsers as $user) {
            $this->notifyStatusChange($user, $validated['status']);
        }

        return response()->json([
            'success' => true,
            'message' => $changedUsers->count() . ' user(s) updated.',
            'data' => [],
        ]);
    }

    private function notifyStatusChange(User $user, string $newStatus): void
    {
        try {
            if ($newStatus === 'approved') {
                $user->notify(new UserApprovedNotification());
            } elseif ($newStatus === 'blocked') {
                $user->notify(new UserBlockedNotification());
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
