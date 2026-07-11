<?php

namespace App\Services;

use App\Events\MessageDelivered;
use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Models\Message;

/**
 * Centralized dispatcher for all message-related broadcast events.
 *
 * Instead of scattering broadcast() calls across controllers and
 * services, every message event flows through this manager so
 * behaviour is consistent, testable, and easy to extend.
 */
class MessageEventManager
{
    public function __construct(
        protected MessageStatusService $statusService,
    ) {}

    /**
     * Broadcast a new message to all participants except the sender.
     *
     * Also updates the conversation's last_activity_at timestamp.
     */
    public function messageSent(Message $message): void
    {
        $message->conversation->update(['last_activity_at' => now()]);

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Mark a message as delivered for a user and broadcast the receipt.
     */
    public function messageDelivered(Message $message, int $userId): void
    {
        $this->statusService->markAsDelivered($message->id, $userId);

        try {
            broadcast(new MessageDelivered(
                messageId: $message->id,
                conversationId: $message->conversation_id,
                deliveredByUserId: $userId,
            ))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Mark all messages in a conversation as read for a user and broadcast the receipt.
     *
     * Returns the number of status rows that were updated.
     */
    public function messagesRead(int $conversationId, int $userId): int
    {
        $updated = $this->statusService->markConversationAsRead($conversationId, $userId);

        if ($updated > 0) {
            try {
                broadcast(new MessagesRead($conversationId, $userId))->toOthers();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $updated;
    }
}
