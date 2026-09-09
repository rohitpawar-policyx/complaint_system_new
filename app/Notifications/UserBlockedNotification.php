<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserBlockedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Account blocked',
            'message' => 'Your account has been blocked. Contact an administrator for more information.',
            'related_type' => 'user',
            'related_id' => $notifiable->id,
        ];
    }
}
