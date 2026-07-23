<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageEventManager;
use App\Services\MessageStatusService;
use Illuminate\Http\Request;

class MessageStatusController extends Controller
{
    /**
     * POST /api/v1/messages/{id}/deliver
     *
     * Mark a single message as delivered for the authenticated user.
     * Only moves from 'sent' → 'delivered'. Does NOT move backward.
     *
     * Security: verifies the user is a participant in the conversation
     * before allowing the status update.
     */
    public function deliver(Request $request, int $messageId)
    {
        $user = $request->user();
        $message = Message::findOrFail($messageId);
        $conversation = $message->conversation;

        // Security: only participants can mark messages as delivered
        if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this conversation.',
            ], 403);
        }

        app(MessageEventManager::class)->messageDelivered($message, $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Message marked as delivered.',
        ]);
    }

    /**
     * POST /api/v1/conversations/{id}/read
     *
     * Mark ALL messages in a conversation as read for the authenticated user.
     * Broadcasts MessagesRead event so senders see read receipts in real time.
     *
     * Security: only participants can mark messages as read.
     */
    public function markConversationRead(Request $request, int $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Security: only participants can mark messages as read
        if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this conversation.',
            ], 403);
        }

        $updated = app(MessageEventManager::class)
            ->messagesRead($conversationId, $user->id);

        return response()->json([
            'success' => true,
            'message' => "{$updated} message(s) marked as read.",
            'data' => [
                'updated_count' => $updated,
            ],
        ]);
    }

    /**
     * GET /api/v1/conversations/{id}/statuses
     *
     * Returns the aggregated delivery status for every message in the conversation.
     * The desktop client polls this to show sent/delivered/read indicators.
     *
     * Response shape:
     *   {
     *     "data": [
     *       { "message_id": 1, "status": "read", "status_label": "Read" },
     *       { "message_id": 2, "status": "delivered", "status_label": "Delivered" },
     *       { "message_id": 3, "status": "sent", "status_label": "Sent" }
     *     ]
     *   }
     *
     * The status is the aggregated worst-case across all recipients:
     *   "read"      → every recipient has read it
     *   "delivered"  → at least one recipient has read/received, but not all read
     *   "sent"       → no recipient has received or read it yet
     */
    public function statuses(Request $request, int $conversationId)
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this conversation.',
            ], 403);
        }

        $messages = $conversation->messages()
            ->with('statusRows')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $message) => [
                'message_id' => $message->id,
                'status' => $message->delivery_status,
                'status_label' => $message->delivery_status_label,
            ]);

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * GET /api/v1/me/unread-counts
     *
     * Returns total unread count and per-conversation unread counts
     * for the authenticated user.
     */
    public function unreadCounts(Request $request)
    {
        $user = $request->user();

        $counts = app(MessageStatusService::class)->getUnreadCounts($user->id);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $counts['total'],
                'per_conversation' => $counts['per_conversation'],
            ],
        ]);
    }
}
