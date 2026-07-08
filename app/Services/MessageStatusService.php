<?php

namespace App\Services;

use App\Events\MessagesRead;
use App\Models\Message;
use App\Models\MessageStatus;

/**
 * Single source of truth for message status transitions (sent → delivered → read).
 *
 * Every controller endpoint calls into this service rather than updating
 * message_status rows directly.
 */
class MessageStatusService
{
    /**
     * Create initial 'sent' status rows for every participant except the sender.
     *
     * Called automatically by Message::booted() created hook.
     * Uses a single insert() for performance with large group conversations.
     */
    public function createInitialStatusRows(Message $message): void
    {
        $participantIds = $message->conversation->participants()
            ->where('user_id', '!=', $message->sender_id)
            ->pluck('user_id');

        if ($participantIds->isEmpty()) {
            return;
        }

        $rows = $participantIds->map(fn (int $userId) => [
            'message_id' => $message->id,
            'user_id' => $userId,
            'status' => 'sent',
        ]);

        MessageStatus::insert($rows->toArray());
    }

    /**
     * Transition a specific message from 'sent' to 'delivered' for a user.
     *
     * Only moves forward: if already 'delivered' or 'read', does nothing.
     */
    public function markAsDelivered(int $messageId, int $userId): void
    {
        MessageStatus::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('status', 'sent')
            ->update(['status' => 'delivered', 'updated_at' => now()]);
    }

    /**
     * Mark ALL messages in a conversation as 'read' for a user in one batch.
     *
     * Broadcasts MessagesRead event so the sender sees read receipts in real time.
     * Returns the number of status rows that were updated.
     */
    public function markConversationAsRead(int $conversationId, int $userId): int
    {
        $updated = MessageStatus::whereIn('message_id', function ($q) use ($conversationId) {
            $q->select('id')->from('messages')
                ->where('conversation_id', $conversationId);
        })
            ->where('user_id', $userId)
            ->whereIn('status', ['sent', 'delivered'])
            ->update(['status' => 'read', 'updated_at' => now()]);

        if ($updated > 0) {
            try {
                broadcast(new MessagesRead($conversationId, $userId))
                    ->toOthers();
            } catch (\Throwable $e) {
                // Broadcasting failure should not block the read-marking
                report($e);
            }
        }

        return $updated;
    }

    /**
     * Get unread counts — both total and per-conversation — for a user.
     *
     * Returns:
     *   ['total' => int, 'per_conversation' => Collection {conversation_id => count}]
     */
    public function getUnreadCounts(int $userId): array
    {
        $perConversation = MessageStatus::whereIn('message_id', function ($q) {
            $q->select('id')->from('messages');
        })
            ->where('user_id', $userId)
            ->whereIn('status', ['sent', 'delivered'])
            ->join('messages', 'message_status.message_id', '=', 'messages.id')
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id, count(*) as unread_count')
            ->pluck('unread_count', 'conversation_id');

        return [
            'total' => $perConversation->sum(),
            'per_conversation' => $perConversation,
        ];
    }
}
