@php
    $unreadCount = auth()->user()->unreadNotifications()->count();
@endphp
<div class="notification-bell" data-notification-bell
    data-notify-feed="{{ route('notifications.feed') }}"
    data-notify-handler-read="{{ url('/notifications') }}"
    data-notify-handler-read-all="{{ route('notifications.readAll') }}"
    data-notify-list="{{ route('notifications.index') }}"
    data-csrf-token="{{ csrf_token() }}">
    <button type="button" class="notification-bell-toggle" data-notification-toggle aria-haspopup="true" aria-expanded="false" aria-label="Notifications">
        <x-icon name="bell" />
        <span class="notification-badge" data-notification-badge @if($unreadCount === 0) hidden @endif>{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
    </button>
    <div class="notification-dropdown" data-notification-dropdown hidden>
        <div class="notification-dropdown-header">
            <span>Notifications</span>
            <button type="button" class="notification-mark-all" data-notification-mark-all>Mark all as read</button>
        </div>
        <div class="notification-dropdown-list" data-notification-list>
            <p class="notification-dropdown-status">Loading…</p>
        </div>
        <a href="{{ route('notifications.index') }}" class="notification-dropdown-footer">View all notifications</a>
    </div>
</div>
