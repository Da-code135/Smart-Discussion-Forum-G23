<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a message is delivered (seen by a recipient) for the first time.
 *
 * Broadcasts to the conversation channel so the sender sees
 * the delivery receipt update in real time.
 */
class MessageDelivered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $messageId,
        public int $conversationId,
        public int $deliveredByUserId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->conversationId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'delivered_by_user_id' => $this->deliveredByUserId,
        ];
    }
}
