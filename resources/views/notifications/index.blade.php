@php $isAdmin = auth()->user()->isAdmin(); @endphp
<x-dynamic-component :component="$isAdmin ? 'layouts.admin' : 'layouts.app'" title="Notifications">
    <section class="page-heading">
        <p class="eyebrow">Account</p>
        <h1>Notifications</h1>
        <p>Everything the system has notified you about.</p>
    </section>
    @if ($notifications->total() > 0)
        <form method="post" action="{{ route('notifications.readAll') }}" class="bulk-form">
            @csrf
            <button type="submit" class="button-link"><x-icon name="check-circle" /> Mark all as read</button>
        </form>
    @endif
    @if ($notifications->isEmpty())
        <section class="content-card empty-state">
            <h2>No notifications yet</h2>
            <p>You'll see complaint and account updates here as they happen.</p>
        </section>
    @else
        <ul class="notification-list notification-list--page">
            @foreach ($notifications as $notification)
                @php $url = \App\Support\NotificationLinks::urlFor($notification); @endphp
                <li class="notification-item{{ $notification->read_at === null ? ' notification-item--unread' : '' }}">
                    <div class="notification-item-body">
                        <div class="notification-item-header">
                            <strong>{{ $notification->data['title'] ?? '' }}</strong>
                            <time>{{ $notification->created_at }}</time>
                        </div>
                        <p class="notification-item-message">{{ $notification->data['message'] ?? '' }}</p>
                        @if ($url !== null)
                            <a href="{{ $url }}" class="notification-item-link">View details</a>
                        @endif
                    </div>
                    @if ($notification->read_at === null)
                        <form method="post" action="{{ route('notifications.read', $notification->id) }}" class="notification-item-action">
                            @csrf
                            <button type="submit" aria-label="Mark as read" title="Mark as read"><x-icon name="check" /></button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        <x-pagination-links :paginator="$notifications" />
    @endif
</x-dynamic-component>
