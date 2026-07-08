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
    // Only authenticated participants of that conversation can listen.
    // Must also belong to the same group (group isolation at schema level).
    return Conversation::where('id', $conversationId)
        ->where('group_id', $user->group_id)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});
