<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast immediately (ShouldBroadcastNow, not ShouldBroadcast) rather than
 * via the queue - this app runs QUEUE_CONNECTION=sync with no queue worker
 * process on Render, so relying on the default queued broadcast would mean
 * "immediately" only holds by coincidence, not by design.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
        $this->message->loadMissing(['sender', 'conversation']);
    }

    /**
     * The channel this event broadcasts on. Keyed by complaint_id (not
     * conversation_id) so the frontend can subscribe as soon as the
     * complaint page loads, before a conversation necessarily exists yet.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.{$this->message->conversation->complaint_id}"),
        ];
    }

    /**
     * Without this, Echo would expect the default "ChatMessageSent" event
     * name (namespaced Laravel-style); this keeps the frontend listener
     * name short and stable regardless of where the class lives.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * The exact JSON payload the browser receives - deliberately explicit
     * rather than relying on default model serialization, so the frontend
     * contract doesn't silently change if the model's attributes do.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'message' => $this->message->message,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender->name,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
