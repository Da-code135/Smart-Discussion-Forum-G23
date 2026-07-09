<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // Participant must be part of the conversation.
    $query = Conversation::where('id', $conversationId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id));

    // System Admins can listen to any conversation they're a participant of.
    // Regular users are scoped to their own group.
    if (! $user->isSystemAdmin()) {
        $query->where('group_id', $user->group_id);
    }

    return $query->exists();
});
