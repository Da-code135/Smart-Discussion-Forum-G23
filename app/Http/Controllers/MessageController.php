<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageEventManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    /**
     * Fetch paginated messages for a conversation, oldest first.
     *
     * GET /conversations/{id}/messages
     * GET /api/v1/conversations/{id}/messages
     */
    public function index(Request $request, int $conversationId): View|JsonResponse
    {
        // 1. Verify the user is a participant
        $conversation = Conversation::forUserInGroup(auth()->user())
            ->whereHas('participants', fn ($q) => $q->where('user_id', auth()->id()))
            ->findOrFail($conversationId);

        // 2. Fetch messages in chronological order, paginated
        $messages = $conversation->messages()
            ->with('sender:id,full_name')
            ->orderBy('created_at')
            ->paginate(50);

        if ($request->is('api/*')) {
            return response()->json(['data' => $messages], 200);
        }

        // For web requests, redirect to the conversation show page which will load messages
        return redirect()->route('conversations.show', $conversationId);
    }

    /**
     * Send a new message.
     *
     * POST /conversations/{id}/messages
     * POST /api/v1/conversations/{id}/messages
     */
    public function store(Request $request, int $conversationId): JsonResponse|RedirectResponse
    {
        // 1. Validate
        $validated = $request->validate([
            'body' => 'required|string|max:10000', // same limit as Forum posts
        ]);

        // 2. Verify participant status
        $conversation = Conversation::forUserInGroup(auth()->user())
            ->whereHas('participants', fn ($q) => $q->where('user_id', auth()->id()))
            ->findOrFail($conversationId);

        // 3. Create message
        $message = $conversation->messages()->create([
            'sender_id' => auth()->id(),
            'body' => $validated['body'],
        ])->load('sender:id,full_name');

        // 4. Broadcast the event and update last_activity_at
        app(MessageEventManager::class)->messageSent($message);

        // 6. Return the message (AJAX/API: JSON, web: redirect with success)
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->full_name,
                    'body' => $message->body,
                    'created_at' => $message->created_at->toIso8601String(),
                    'updated_at' => $message->updated_at->toIso8601String(),
                ],
            ], 201);
        }

        return redirect()
            ->back()
            ->with('success', 'Message sent successfully.');
    }

    /**
     * Edit a message (sender only, within 10 minutes of sending).
     *
     * PUT /api/v1/messages/{id}
     *
     * Group isolation enforced via conversation.
     */
    public function update(Request $request, int $messageId): JsonResponse
    {
        $user = $request->user();

        $message = Message::with('conversation')->findOrFail($messageId);

        // Group isolation check via conversation
        if (! $user->canAccessGroup($message->conversation->group_id)) {
            return response()->json(
                ['message' => 'You do not have access to this message.'],
                403,
            );
        }

        // Only the sender can edit their own message
        if ($message->sender_id !== $user->id) {
            return response()->json(
                ['message' => 'You can only edit your own messages.'],
                403,
            );
        }

        // Cannot edit removed messages
        if ($message->is_removed) {
            return response()->json(
                ['message' => 'This message has been removed and cannot be edited.'],
                403,
            );
        }

        // Must be within 10 minutes of sending
        if ($message->created_at->diffInMinutes(now()) > 10) {
            return response()->json(
                ['message' => 'Messages can only be edited within 10 minutes of sending.'],
                403,
            );
        }

        $validated = $request->validate([
            'body' => 'required|string|max:10000',
        ]);

        $message->update(['body' => $validated['body']]);

        return response()->json([
            'message' => 'Message updated successfully.',
            'data' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_id' => $message->sender_id,
                'body' => $message->body,
                'created_at' => $message->created_at->toIso8601String(),
                'updated_at' => $message->updated_at->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Soft-delete a message (sender or conversation admin).
     *
     * DELETE /api/v1/messages/{id}
     *
     * Sets is_removed = true. Group isolation enforced via conversation.
     */
    public function destroy(Request $request, int $messageId): JsonResponse
    {
        $user = $request->user();

        $message = Message::with('conversation')->findOrFail($messageId);

        // Group isolation check via conversation
        if (! $user->canAccessGroup($message->conversation->group_id)) {
            return response()->json(
                ['message' => 'You do not have access to this message.'],
                403,
            );
        }

        // Authorization: sender or conversation admin
        $isSender = $message->sender_id === $user->id;
        $isAdmin = $message->conversation->participants()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'admin')
            ->exists();

        if (! $isSender && ! $isAdmin && ! $user->isSystemAdmin()) {
            return response()->json(
                ['message' => 'You are not authorized to delete this message.'],
                403,
            );
        }

        $message->update(['is_removed' => true]);

        return response()->json([
            'message' => 'Message deleted successfully.',
        ], 200);
    }
}
