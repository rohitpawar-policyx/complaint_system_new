<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Resolves a database notification to a URL. Unlike the previous
 * hand-computed relative-path system, this uses Laravel's named routes,
 * so it is correct regardless of which page renders the notification.
 */
class NotificationLinks
{
    public static function urlFor(DatabaseNotification $notification): ?string
    {
        $relatedType = $notification->data['related_type'] ?? null;
        $relatedId = $notification->data['related_id'] ?? null;

        if ($relatedId === null) {
            return null;
        }

        if ($relatedType === 'complaint') {
            if ($notification->type === \App\Notifications\ComplaintCreatedNotification::class) {
                return route('admin.complaints.show', $relatedId);
            }

            return route('complaints.show', $relatedId);
        }

        if ($relatedType === 'user') {
            return route('profile.edit');
        }

        return null;
    }
}
