<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    /**
     * List all conversations the authenticated user participates in.
     *
     * GET /conversations
     * GET /api/v1/conversations
     */
    public function index(Request $request): View|JsonResponse
    {
        $conversations = Conversation::forUserInGroup(auth()->user())
            ->with([
                'participants:id,full_name',
                'lastMessage:id,conversation_id,body,created_at',
            ])
            ->orderByDesc('last_activity_at')
            ->paginate(20);

        if ($request->is('api/*')) {
            return response()->json(['data' => $conversations], 200);
        }

        return view('conversations.index', compact('conversations'));
    }

    /**
     * Show the form to create a new conversation.
     *
     * GET /conversations/create
     */
    public function create(Request $request): View
    {
        $currentUser = auth()->user();

        $usersQuery = User::where('id', '!=', $currentUser->id)
            ->whereNull('blacklisted_at');

        // System Admins see all users; others see only their group
        if (! $currentUser->isSystemAdmin()) {
            $usersQuery->where('group_id', $currentUser->group_id);
        }

        $users = $usersQuery->orderBy('full_name')
            ->get(['id', 'full_name']);

        return view('conversations.create', compact('users'));
    }

    /**
     * Show a single conversation's metadata (participants, name, type).
     * Not the messages — that's Person 3's job.
     *
     * GET /conversations/{id}
     * GET /api/v1/conversations/{id}
     */
    public function show(Request $request, int $id): View|JsonResponse
    {
        $conversation = Conversation::forUserInGroup(auth()->user())
            ->whereHas('participants', fn ($q) => $q->where('user_id', auth()->id()))
            ->with('participants:id,full_name')
            ->findOrFail($id);

        if ($request->is('api/*')) {
            return response()->json(['data' => $conversation], 200);
        }

        return view('conversations.show', compact('conversation'));
    }

    /**
     * Start a new conversation — either direct (1-to-1) or group (3+).
     *
     * POST /conversations
     * POST /api/v1/conversations
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:direct,group',
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'exists:users,id',
            'name' => 'required_if:type,group|string|max:255',
        ]);

        $currentUser = auth()->user();

        // --- Determine target group ---
        // System Admins must pick a group; others use their own
        if ($currentUser->isSystemAdmin()) {
            $targetGroupId = $request->has('group_id')
                ? (int) $request->input('group_id')
                : User::find($validated['participant_ids'][0])?->group_id;

            if (! $targetGroupId) {
                $error = 'A group_id is required or could not be inferred from participants.';

                if ($request->is('api/*')) {
                    return response()->json(['message' => $error], 422);
                }

                return back()->withErrors(['group_id' => $error])->withInput();
            }
        } else {
            $targetGroupId = $currentUser->group_id;

            // --- Cross-group check: all participants must be in the same group ---
            foreach ($validated['participant_ids'] as $userId) {
                $otherUser = User::findOrFail($userId);
                if ($otherUser->group_id !== $targetGroupId) {
                    $error = "User {$otherUser->full_name} is not in your group. ".
                        'Conversations are limited to group members only.';

                    if ($request->is('api/*')) {
                        return response()->json(['message' => $error], 422);
                    }

                    return back()->withErrors(['participant_ids' => $error])->withInput();
                }
            }
        }

        // --- Duplicate direct conversation check ---
        if ($validated['type'] === 'direct') {
            $otherUserId = $validated['participant_ids'][0];
            $existing = Conversation::where('type', 'direct')
                ->whereHas('participants', fn ($q) => $q->where('user_id', auth()->id()))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $otherUserId))
                ->whereDoesntHave('participants', fn ($q) => $q->whereNotIn('user_id', [auth()->id(), $otherUserId]));

            // Non-admins are scoped to their group; System Admins cross-group
            if (! $currentUser->isSystemAdmin()) {
                $existing->where('group_id', $targetGroupId);
            }

            $existing = $existing->first();

            if ($existing) {
                $existing->load([
                    'participants:id,full_name',
                    'lastMessage:id,conversation_id,body,created_at',
                ]);

                if ($request->is('api/*')) {
                    return response()->json(['data' => $existing], 200);
                }

                return redirect()->route('conversations.show', $existing);
            }
        }

        // --- Create the conversation ---
        $conversation = Conversation::create([
            'group_id' => $targetGroupId,
            'type' => $validated['type'],
            'name' => $validated['name'] ?? null,
            'last_activity_at' => now(),
        ]);

        // Attach creator with admin role for group conversations
        $creatorRole = $validated['type'] === 'group' ? 'admin' : 'participant';
        $conversation->participants()->attach(auth()->id(), [
            'role' => $creatorRole,
            'joined_at' => now(),
        ]);

        // Attach other participants
        foreach ($validated['participant_ids'] as $userId) {
            if ((int) $userId !== auth()->id()) {
                $conversation->participants()->attach($userId, [
                    'role' => 'participant',
                    'joined_at' => now(),
                ]);
            }
        }

        $conversation->load([
            'participants:id,full_name',
            'lastMessage:id,conversation_id,body,created_at',
        ]);

        if ($request->is('api/*')) {
            return response()->json(['data' => $conversation], 201);
        }

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('success', 'Conversation started successfully.');
    }

    /**
     * Add a participant to an existing group conversation.
     *
     * POST /conversations/{id}/participants
     * POST /api/v1/conversations/{id}/participants
     */
    public function addParticipant(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $conversation = Conversation::findOrFail($id);

        // Only group conversations allow participant management
        if ($conversation->type !== 'group') {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'Cannot add participants to a direct conversation.'],
                    422,
                );
            }

            return back()->with('error', 'Cannot add participants to a direct conversation.');
        }

        // Only the creator or an admin participant can manage members
        $currentUser = $conversation->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (! $currentUser || $currentUser->pivot->role !== 'admin') {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'Only conversation admins can manage participants.'],
                    403,
                );
            }

            abort(403, 'Only conversation admins can manage participants.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Cross-group check
        $newUser = User::findOrFail($validated['user_id']);
        if ($newUser->group_id !== $conversation->group_id) {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'Cannot add a user from a different group to this conversation.'],
                    422,
                );
            }

            return back()->with('error', 'Cannot add a user from a different group to this conversation.');
        }

        // Skip if already a participant
        $alreadyParticipant = $conversation->participants()
            ->where('user_id', $newUser->id)
            ->exists();

        if (! $alreadyParticipant) {
            $conversation->participants()->attach($newUser->id, [
                'role' => 'participant',
                'joined_at' => now(),
            ]);
            $conversation->touchQuietly(); // updates updated_at
            $conversation->update(['last_activity_at' => now()]);
        }

        if ($request->is('api/*')) {
            return response()->json(['message' => 'Participant added.'], 200);
        }

        return back()->with('success', 'Participant added.');
    }

    /**
     * Remove a participant from an existing group conversation.
     *
     * DELETE /conversations/{id}/participants/{userId}
     * DELETE /api/v1/conversations/{id}/participants/{userId}
     */
    public function removeParticipant(Request $request, int $id, int $userId): JsonResponse|RedirectResponse
    {
        $conversation = Conversation::findOrFail($id);

        // Only group conversations allow participant management
        if ($conversation->type !== 'group') {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'Cannot remove participants from a direct conversation.'],
                    422,
                );
            }

            return back()->with('error', 'Cannot remove participants from a direct conversation.');
        }

        // Only an admin participant can manage members
        $currentUser = $conversation->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (! $currentUser || $currentUser->pivot->role !== 'admin') {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'Only conversation admins can manage participants.'],
                    403,
                );
            }

            abort(403, 'Only conversation admins can manage participants.');
        }

        // Cannot remove yourself
        if ((int) $userId === auth()->id()) {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'You cannot remove yourself from the conversation.'],
                    422,
                );
            }

            return back()->with('error', 'You cannot remove yourself from the conversation.');
        }

        // Verify target is a participant
        $targetUser = $conversation->participants()
            ->where('user_id', $userId)
            ->first();

        if (! $targetUser) {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'User is not a participant of this conversation.'],
                    422,
                );
            }

            return back()->with('error', 'User is not a participant of this conversation.');
        }

        $conversation->participants()->detach($userId);
        $conversation->update(['last_activity_at' => now()]);

        if ($request->is('api/*')) {
            return response()->json(['message' => 'Participant removed.'], 200);
        }

        return back()->with('success', 'Participant removed.');
    }
}
