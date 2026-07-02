<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Barryvdh\DomPDF\Facade\Pdf;

class SharedTopicController extends Controller
{
    /**
     * Show a shared topic using a signed URL
     *
     * @param Request $request
     * @param Topic $topic
     * @param int $signedUserId
     * @param int $expires
     * @param string $signature
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Topic $topic, int $signedUserId, int $expires, string $signature)
    {
        // Verify the signed URL
        $url = URL::signedRoute('shared.topic.show', [
            'topic' => $topic->id,
            'signedUserId' => $signedUserId,
            'expires' => $expires
        ]);

        if (!URL::hasValidSignature($request, $expires, $signature)) {
            abort(403, 'Invalid or expired signature');
        }

        // Get the user who shared the topic
        $sharingUser = User::findOrFail($signedUserId);

        // Check if the topic exists and is active
        if ($topic->status !== 'active') {
            abort(404, 'Topic not found or not active');
        }

        // Load the topic with its creator
        $topic->load(['creator']);

        // Load all visible, non-removed replies with their authors
        $replies = $topic->posts()
            ->notRemoved()
            ->visibleToUser($sharingUser->id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        return view('forum.shared-topic', [
            'topic' => $topic,
            'replies' => $replies,
            'sharingUser' => $sharingUser
        ]);
    }
}