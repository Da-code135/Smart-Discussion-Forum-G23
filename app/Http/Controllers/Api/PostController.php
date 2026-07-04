<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Topic;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * P1: Create a reply (post) in a topic.
     *
     * POST /api/v1/topics/{topicId}/posts
     *
     * Group isolation enforced. Topic must be active.
     */
    public function store(Request $request, int $topicId)
    {
        $user = $request->user();

        $topic = Topic::findOrFail($topicId);

        // Group isolation check (SysAdmin bypass)
        if ($topic->group_id !== $user->group_id && !$user->isSystemAdmin()) {
            return response()->json(
                [
                    "message" => "You do not have access to this topic.",
                ],
                403,
            );
        }

        // Only active topics accept replies
        if ($topic->status !== "active") {
            return response()->json(
                [
                    "message" => "This topic is closed for replies.",
                ],
                403,
            );
        }

        $validated = $request->validate([
            "content" => "required|string|max:10000",
        ]);

        $post = Post::create([
            "topic_id" => $topic->id,
            "user_id" => $user->id,
            "content" => $validated["content"],
        ]);

        // Notify the original asker when a question is answered
        if (
            $topic->post_type === "question" &&
            $topic->created_by !== $user->id
        ) {
            Notification::create([
                "user_id" => $topic->created_by,
                "type" => "question_answered",
                "data" => ["topic_id" => $topic->id, "post_id" => $post->id],
            ]);
        }

        // Auto-mark question as answered
        if ($topic->post_type === "question" && !$topic->is_answered) {
            $topic->update(["is_answered" => true]);
        }

        $post->load("user");

        return response()->json(
            [
                "message" => "Reply posted successfully.",
                "data" => [
                    "post" => [
                        "id" => $post->id,
                        "topic_id" => $post->topic_id,
                        "content" => $post->content,
                        "user" => [
                            "id" => $post->user->id,
                            "full_name" => $post->user->full_name,
                        ],
                        "created_at" => $post->created_at,
                        "updated_at" => $post->updated_at,
                    ],
                ],
            ],
            201,
        );
    }

    /**
     * P2: Update own post content.
     *
     * PUT /api/v1/posts/{postId}
     *
     * Only the post author can update their post.
     * Group isolation enforced via topic.
     */
    public function update(Request $request, int $postId)
    {
        $user = $request->user();

        $post = Post::with("topic")->findOrFail($postId);

        // Group isolation check via topic (SysAdmin bypass)
        if (
            $post->topic->group_id !== $user->group_id &&
            !$user->isSystemAdmin()
        ) {
            return response()->json(
                [
                    "message" => "You do not have access to this post.",
                ],
                403,
            );
        }

        // Authorization: only the post author
        if ($post->user_id !== $user->id) {
            return response()->json(
                [
                    "message" => "You can only edit your own posts.",
                ],
                403,
            );
        }

        // Cannot edit removed posts
        if ($post->is_removed) {
            return response()->json(
                [
                    "message" =>
                        "This post has been removed and cannot be edited.",
                ],
                403,
            );
        }

        $validated = $request->validate([
            "content" => "required|string|max:10000",
        ]);

        $post->update(["content" => $validated["content"]]);
        $post->load("user");

        return response()->json(
            [
                "message" => "Post updated successfully.",
                "data" => [
                    "post" => [
                        "id" => $post->id,
                        "topic_id" => $post->topic_id,
                        "content" => $post->content,
                        "user" => [
                            "id" => $post->user->id,
                            "full_name" => $post->user->full_name,
                        ],
                        "created_at" => $post->created_at,
                        "updated_at" => $post->updated_at,
                    ],
                ],
            ],
            200,
        );
    }

    /**
     * P3: Soft-delete own post.
     *
     * DELETE /api/v1/posts/{postId}
     *
     * Only the post author or an admin can delete.
     * Sets is_removed = true. Group isolation enforced via topic.
     */
    public function destroy(
        Request $request,
        int $postId,
        AuditLogService $auditLog,
    ) {
        $user = $request->user();

        $post = Post::with("topic")->findOrFail($postId);

        // Group isolation check via topic (SysAdmin bypass)
        if (
            $post->topic->group_id !== $user->group_id &&
            !$user->isSystemAdmin()
        ) {
            return response()->json(
                [
                    "message" => "You do not have access to this post.",
                ],
                403,
            );
        }

        // Authorization: post author or admin
        if ($post->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(
                [
                    "message" => "You are not authorized to delete this post.",
                ],
                403,
            );
        }

        $post->update(["is_removed" => true]);

        $auditLog->log(
            action: "post.deleted",
            target: $post,
            newValues: ["is_removed" => true],
            description: $user->full_name .
                " deleted post #" .
                $post->id .
                ' in topic "' .
                $post->topic->title .
                '"',
        );

        return response()->json(
            [
                "message" => "Post deleted successfully.",
            ],
            200,
        );
    }
}
