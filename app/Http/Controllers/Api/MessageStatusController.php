<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
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
        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this conversation.',
            ], 403);
        }

        app(MessageStatusService::class)->markAsDelivered($messageId, $user->id);

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
        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this conversation.',
            ], 403);
        }

        $updated = app(MessageStatusService::class)
            ->markConversationAsRead($conversationId, $user->id);

        return response()->json([
            'success' => true,
            'message' => "{$updated} message(s) marked as read.",
            'data' => [
                'updated_count' => $updated,
            ],
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
