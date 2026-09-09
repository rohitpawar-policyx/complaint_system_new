<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplaintStatusChangedNotification extends Notification
{
    use Queueable;

    private const STATUS_LABELS = [
        'pending' => 'set back to pending',
        'in_progress' => 'marked in progress',
        'resolved' => 'resolved',
        'closed' => 'closed',
        'rejected' => 'rejected',
    ];

    public function __construct(private readonly Complaint $complaint)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = self::STATUS_LABELS[$this->complaint->status] ?? ('updated to ' . $this->complaint->status);

        return [
            'title' => 'Complaint status updated',
            'message' => 'Your complaint #' . $this->complaint->id . ' has been ' . $label . '.',
            'related_type' => 'complaint',
            'related_id' => $this->complaint->id,
        ];
    }
}
