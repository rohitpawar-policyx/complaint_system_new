<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplaintCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Complaint $complaint)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New complaint submitted',
            'message' => $this->complaint->user->name . ' submitted a new "' . $this->complaint->reason->name
                . '" complaint (#' . $this->complaint->id . ').',
            'related_type' => 'complaint',
            'related_id' => $this->complaint->id,
        ];
    }
}
