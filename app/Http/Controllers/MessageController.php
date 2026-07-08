<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
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

        // 2. Fetch messages in reverse chronological order, paginated
        $messages = $conversation->messages()
            ->with('sender:id,full_name')
            ->orderByDesc('created_at')
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
        ]);

        // 4. Update conversation's last_activity_at
        $conversation->update(['last_activity_at' => now()]);

        // 5. Broadcast the event
        // The event class is defined below
        broadcast(new MessageSent($message))->toOthers();
        // ->toOthers() means the sender doesn't receive their own broadcast

        // 6. Return the message (web: redirect with success, API: JSON)
        if ($request->is('api/*')) {
            return response()->json(['data' => $message], 201);
        }

        return redirect()
            ->back()
            ->with('success', 'Message sent successfully.');
    }
}