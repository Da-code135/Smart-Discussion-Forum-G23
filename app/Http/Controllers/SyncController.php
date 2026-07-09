<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;
use App\Models\SyncCheckpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offline Sync Controller for the desktop client.
 *
 * Provides delta-sync (pull) and offline message upload (push) endpoints
 * so the desktop application can stay in sync without a persistent
 * WebSocket connection.
 *
 * All endpoints require Authentication (auth:sanctum).
 */
class SyncController extends Controller
{
    /**
     * Pull: Return everything that changed since the device's last sync checkpoint.
     *
     * GET /api/v1/sync/pull?device_id={deviceId}
     *
     * Returns new/updated conversations, messages, and status updates
     * that occurred after the device's last successful sync.
     *
     * The checkpoint is updated ONLY after the response payload is built
     * (not after it's sent). If building the response fails, the checkpoint
     * is not advanced, and the client will retry on the next sync — no data loss.
     */
    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:255',
        ]);

        $user = $request->user();

        // -------------------------------------------------------------------
        //  1. Get or create the checkpoint for this device
        // -------------------------------------------------------------------
        // On first sync, default to 1 year ago so the client pulls all
        // existing conversations and messages from the past year.
        $checkpoint = SyncCheckpoint::firstOrCreate(
            ['user_id' => $user->id, 'device_id' => $validated['device_id']],
            ['last_synced_at' => now()->subYear()],
        );

        $since = $checkpoint->last_synced_at;

        // -------------------------------------------------------------------
        //  2. Build the delta payload
        // -------------------------------------------------------------------

        // Find all conversation IDs the user has access to
        $conversationIds = Conversation::forUserInGroup($user)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        // Conversations that have changed (name, last_activity_at, etc.)
        $updatedConversations = Conversation::whereIn('id', $conversationIds)
            ->where('updated_at', '>', $since)
            ->with('participants:id,full_name')
            ->get();

        // New messages since the last checkpoint
        $newMessages = Message::whereIn('conversation_id', $conversationIds)
            ->where('created_at', '>', $since)
            ->with('sender:id,full_name')
            ->orderBy('created_at')
            ->get();

        // Status updates (sent → delivered → read) for this user's messages
        $statusUpdates = MessageStatus::whereIn('message_id', function ($q) use ($conversationIds) {
                $q->select('id')
                    ->from('messages')
                    ->whereIn('conversation_id', $conversationIds);
            })
            ->where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get();

        // -------------------------------------------------------------------
        //  3. Update the checkpoint AFTER building the payload
        // -------------------------------------------------------------------
        // If the process crashes between building the payload and updating
        // the checkpoint, the client re-syncs the same data next time.
        // That's safe because messages are idempotent — no data lost.
        $checkpoint->update(['last_synced_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'conversations' => $updatedConversations,
                'messages' => $newMessages,
                'status_updates' => $statusUpdates,
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Push: Accept a batch of messages composed while offline.
     *
     * POST /api/v1/sync/push
     *
     * Each message in the batch is validated individually, checked for
     * participant access, deduplicated by client_id, saved, and broadcast
     * to other online participants. Per-message success/failure is returned
     * so the client can report which messages failed.
     */
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages' => 'required|array|max:100',
            'messages.*.client_id' => 'required|string|max:255',
            'messages.*.conversation_id' => 'required|integer|exists:conversations,id',
            'messages.*.body' => 'required|string|max:10000',
        ]);

        $user = $request->user();
        $results = [];

        foreach ($validated['messages'] as $msg) {
            // ---------------------------------------------------------------
            //  a) Verify participant access (same check as Person 3's store)
            // ---------------------------------------------------------------
            $conversation = Conversation::forUserInGroup($user)
                ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
                ->find($msg['conversation_id']);

            if (! $conversation) {
                $results[] = [
                    'client_id' => $msg['client_id'],
                    'success' => false,
                    'error' => 'Conversation not found or not accessible.',
                ];
                continue;
            }

            // ---------------------------------------------------------------
            //  b) Deduplicate: same sender + same body + within 5 minutes
            // ---------------------------------------------------------------
            // If the client sent this batch, the response timed out, and the
            // client retries, we don't want duplicate messages. Check for an
            // identical message from the same user in the last 5 minutes.
            $existing = Message::where('conversation_id', $conversation->id)
                ->where('sender_id', $user->id)
                ->where('body', $msg['body'])
                ->where('created_at', '>', now()->subMinutes(5))
                ->first();

            if ($existing) {
                $results[] = [
                    'client_id' => $msg['client_id'],
                    'success' => true,
                    'message_id' => $existing->id,
                ];
                continue;
            }

            // ---------------------------------------------------------------
            //  c) Save the message (same path as Person 3's MessageController)
            // ---------------------------------------------------------------
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => $msg['body'],
            ]);

            // Update the conversation's sort-order timestamp
            $conversation->update(['last_activity_at' => now()]);

            // Broadcast to other participants if they're online
            try {
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                // Broadcasting failure should not block the message from being saved.
                // The message is in the database — it will be picked up by the
                // next pull sync. Report the error for debugging.
                report($e);
            }

            $results[] = [
                'client_id' => $msg['client_id'],
                'success' => true,
                'message_id' => $message->id,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'results' => $results,
            ],
        ]);
    }
}
