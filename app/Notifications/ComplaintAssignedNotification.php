<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplaintAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Complaint $complaint,
        private readonly bool $wasReassignment
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->wasReassignment ? 'Complaint reassigned to you' : 'Complaint assigned to you',
            'message' => 'Complaint #' . $this->complaint->id . ' has been '
                . ($this->wasReassignment ? 're' : '') . 'assigned to you.',
            'related_type' => 'complaint',
            'related_id' => $this->complaint->id,
        ];
    }
}
