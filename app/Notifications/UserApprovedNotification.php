<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserApprovedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Account approved',
            'message' => 'Your account has been approved. You now have full access.',
            'related_type' => 'user',
            'related_id' => $notifiable->id,
        ];
    }
}
