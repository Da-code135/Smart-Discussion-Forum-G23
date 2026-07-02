<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\Request;

class SharedTopicController extends Controller
{
    /**
     * Show a shared topic using a temporary signed URL.
     */
    public function show(Request $request, Topic $topic, int $signedUserId)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired share link.');
        }

        $sharingUser = User::findOrFail($signedUserId);

        if ($topic->status !== 'active') {
            abort(404, 'Topic not found or not active');
        }

        $topic->load(['creator']);

        $replies = $topic->posts()
            ->notRemoved()
            ->visibleToUser($sharingUser->id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        return view('forum.shared-topic', [
            'topic' => $topic,
            'replies' => $replies,
            'sharingUser' => $sharingUser,
        ]);
    }
}
