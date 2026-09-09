<?php

namespace App\Http\Controllers;

use App\Support\NotificationLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return view('notifications.index', ['notifications' => $notifications]);
    }

    // GET, read-only, session-scoped — no CSRF needed.
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $recent = $user->notifications()->latest()->limit(8)->get()->map(function ($notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? '',
                'message' => $notification->data['message'] ?? '',
                'is_read' => $notification->read_at !== null,
                'created_at' => $notification->created_at->toDateTimeString(),
                'path' => NotificationLinks::urlFor($notification),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => [
                'unread_count' => $user->unreadNotifications()->count(),
                'notifications' => $recent,
            ],
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        // Ownership is enforced by scoping to the authenticated user's own
        // notifications relation — a foreign/invalid id simply matches
        // nothing rather than revealing whether it exists.
        $request->user()->notifications()->where('id', $notification)->whereNull('read_at')->update(['read_at' => now()]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.',
                'data' => ['unread_count' => $request->user()->unreadNotifications()->count()],
            ]);
        }

        return back();
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.',
                'data' => ['unread_count' => 0],
            ]);
        }

        return back();
    }
}
