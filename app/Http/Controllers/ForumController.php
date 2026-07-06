<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostVisibility;
use App\Models\Topic;
use App\Models\User;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class ForumController extends Controller
{
    /**
     * ============================================
     * Task 2a.3 — Forum Feed (Topic List)
     * ============================================
     *
     * Display all active topics in the authenticated user's group,
     * paginated and ordered by most recent first.
     *
     * Security: group_id is hard-filtered so topics from other groups
     * never leak into this view (defense in depth).
     */
    public function index()
    {
        $user = Auth::user();

        // Build the query: active topics only
        $query = Topic::where('status', 'active')
            ->with('creator')
            ->withCount('posts');

        // Group isolation: System Admins see all topics;
        // others (including Lecturers with cross-group access) see only accessible groups
        if (! $user->isSystemAdmin()) {
            $query->whereIn('group_id', $user->accessibleGroupIds());
        }

        $topics = $query->latest()->paginate(10);

        // System Admins may have null group_id — pass the first accessible group
        // for display purposes, or null if truly group-agnostic.
        $group = $user->isSystemAdmin() && ! $user->group_id
            ? Group::orderBy('id')->first()
            : $user->group;

        return view('forum.index', compact('topics', 'group'));
    }

    /**
     * ============================================
     * Task 2a.2 — Show Create Topic Form
     * ============================================
     *
     * Display the form where a member types a title and description
     * for a new discussion topic.
     */
    public function create()
    {
        return view('forum.create-topic');
    }

    /**
     * ============================================
     * Task 2a.2 — Store a New Topic
     * ============================================
     *
     * Validate input, create the Topic record scoped to the user's group,
     * then redirect to the forum feed with a success message.
     *
     * Security: group_id is taken from Auth::user() — the user cannot
     * override it via the form payload. This prevents cross-group topic creation.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // System Admins can choose a target group; regular users are locked to their own
        $targetGroupId = $user->isSystemAdmin()
            ? ($request->input('group_id') ?: optional($user->group)->id)
            : $user->group_id;

        $request->validate([
            'title' => 'required|max:255|unique:topics,title,NULL,id,group_id,'.
                $targetGroupId,
            'description' => 'required|string|max:10000',
            'post_type' => 'sometimes|in:discussion,question',
            'group_id' => [
                'sometimes',
                'integer',
                'exists:groups,id',
                function ($attribute, $value, $fail) use ($user) {
                    if ($user->isSystemAdmin() && ! $user->canAccessGroup($value)) {
                        $fail('You do not have access to this group.');
                    }
                },
            ],
        ]);

        Topic::create([
            'title' => $request->title,
            'description' => $request->description,
            'post_type' => $request->post_type ?? 'discussion',
            'created_by' => $user->id,
            'group_id' => $targetGroupId,
            'status' => 'active',
        ]);

        return redirect()
            ->route('forum.index')
            ->with('success', 'Topic created successfully!');
    }

    /**
     * ============================================
     * Task 6.27 — Show Edit Topic Form
     * ============================================
     *
     * Display the form to edit an existing topic.
     *
     * Security:
     *   1. Group isolation check (SysAdmin bypass)
     *   2. Only the topic creator or an admin can edit
     */
    public function edit(Topic $topic)
    {
        // Group isolation (SysAdmin / Lecturer / Group Admin bypass)
        if (! Auth::user()->canAccessGroup($topic->group_id)) {
            abort(403, 'You do not have access to this topic.');
        }

        // Only topic creator or admin can edit
        if ($topic->created_by !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403, 'You are not authorized to edit this topic.');
        }

        return view('forum.edit-topic', compact('topic'));
    }

    /**
     * ============================================
     * Task 6.27 — Update an Existing Topic
     * ============================================
     *
     * Validate input, update the Topic record.
     *
     * Security:
     *   1. Group isolation check (SysAdmin bypass)
     *   2. Only the topic creator or an admin can update
     *   3. group_id cannot be changed (scoped to original group)
     */
    public function update(Request $request, Topic $topic)
    {
        // Group isolation (SysAdmin / Lecturer / Group Admin bypass)
        if (! Auth::user()->canAccessGroup($topic->group_id)) {
            abort(403, 'You do not have access to this topic.');
        }

        // Only topic creator or admin can update
        if ($topic->created_by !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403, 'You are not authorized to update this topic.');
        }

        $validated = $request->validate([
            'title' => 'required|max:255|unique:topics,title,'.$topic->id.',id,group_id,'.$topic->group_id,
            'description' => 'required|string|max:10000',
            'post_type' => 'sometimes|in:discussion,question',
        ]);

        $oldValues = $topic->only(['title', 'description', 'post_type']);

        $topic->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'post_type' => $validated['post_type'] ?? $topic->post_type,
        ]);

        // Audit log
        app(AuditLogService::class)->log(
            action: 'topic.updated',
            target: $topic,
            oldValues: $oldValues,
            newValues: $topic->only(['title', 'description', 'post_type']),
            description: Auth::user()->full_name.
                ' updated topic "'.
                $topic->title.
                '"',
        );

        return redirect()
            ->route('forum.show', $topic->id)
            ->with('success', 'Topic updated successfully!');
    }

    /**
     * ============================================
     * Task 2b.1 — Topic Detail with Threading
     * ============================================
     *
     * Display a single topic with all its replies (posts).
     *
     * Security — Group isolation check:
     * Even if a user guesses a topic ID from another group,
     * they get a 403 Forbidden. This is the second line of defense
     * (the first is the group_id filter in index()).
     *
     * Performance — Nested eager loading:
     * Loads the topic + all posts + all post authors in exactly
     * 3 queries total, regardless of how many replies exist.
     */
    public function show(Topic $topic)
    {
        // === GROUP ISOLATION CHECK (Defense in depth) ===
        if (! Auth::user()->canAccessGroup($topic->group_id)) {
            abort(403, 'You do not have access to this topic.');
        }

        // Load the topic with its group
        $topic->load(['group']);

        // === PAGINATED POSTS ===
        // Load replies with pagination instead of eager-loading all at once
        $posts = Post::where('topic_id', $topic->id)
            ->notRemoved()
            ->visibleToUser(Auth::id())
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        // Pre-load users eligible for exclusion (same group as the topic, not current user)
        // For System Admins (who may have null group_id), we use the topic's group instead.
        $excludableUsers = User::where('group_id', $topic->group_id)
            ->where('id', '!=', Auth::id())
            ->get();

        return view('forum.show', compact('topic', 'posts', 'excludableUsers'));
    }

    /**
     * ============================================
     * Task 2b.2 — Store a Reply
     * ============================================
     *
     * Create a new Post record as a reply to the given topic.
     *
     * Security checks:
     *   1. Topic must belong to the user's group (group isolation)
     *   2. Topic must be active (not archived)
     *
     * Design note: There is no separate 'replies' table.
     * Posts ARE the replies — each Post record with a topic_id
     * is a reply to that topic. The topic's own 'description'
     * field serves as the opening post content.
     */
    public function replyStore(Request $request, Topic $topic)
    {
        // === GROUP ISOLATION CHECK ===
        if (! Auth::user()->canAccessGroup($topic->group_id)) {
            abort(403, 'You do not have access to this topic.');
        }

        // === STATUS CHECK — Only active topics accept replies ===
        if ($topic->status !== 'active') {
            return back()->with('error', 'This topic is closed for replies.');
        }

        $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => Auth::id(),
            'content' => $request->content,
        ]);

        // Log the reply for audit trail
        app(AuditLogService::class)->log(
            action: 'post.created',
            target: $post,
            newValues: $post->toArray(),
            description: Auth::user()->full_name.
                ' replied to topic "'.
                $topic->title.
                '"',
        );

        $replyAuthor = Auth::user();

        // Notify the original asker when a question is answered
        if (
            $topic->post_type === 'question' &&
            $topic->created_by !== $replyAuthor->id
        ) {
            Notification::create([
                'user_id' => $topic->created_by,
                'type' => 'question_answered',
                'data' => ['topic_id' => $topic->id, 'post_id' => $post->id],
            ]);
        }

        // Auto-mark question as answered
        if ($topic->post_type === 'question' && ! $topic->is_answered) {
            $topic->update(['is_answered' => true]);
        }

        return redirect()
            ->route('forum.show', $topic->id)
            ->with('success', 'Reply posted successfully!');
    }

    /**
     * ============================================
     * Task 4.1 — Exclude User from Post Visibility
     * ============================================
     *
     * Allow the post author to exclude specific users from seeing their post.
     * This creates a record in the post_visibility table to hide the post from the excluded user.
     *
     * Security: Users can only exclude others in their own group
     */
    public function excludeUser(Request $request, Post $post)
    {
        // Only the post author can exclude users
        if ($post->user_id !== Auth::id()) {
            abort(403, 'Only the post author can exclude users.');
        }

        // Validate the user_id input
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Get the user to be excluded
        $userToExclude = User::findOrFail($request->user_id);

        // Ensure the user being excluded belongs to the same group as the post author
        if ($userToExclude->group_id !== Auth::user()->group_id) {
            abort(403, 'You can only exclude users in your own group.');
        }

        // Check if rule already exists
        $existing = PostVisibility::where('post_id', $post->id)
            ->where('excluded_user_id', $request->user_id)
            ->first();

        if (! $existing) {
            PostVisibility::create([
                'post_id' => $post->id,
                'excluded_user_id' => $request->user_id,
            ]);
        }

        return redirect()
            ->back()
            ->with([
                'success' => 'User excluded from this post.',
                'post_id' => $post->id,
            ]);
    }

    /**
     * ============================================
     * Task 5.1 — Export Topic Thread as PDF
     * ============================================
     *
     * Generate a formatted PDF of the topic thread including the opening
     * post (topic description) and all visible replies.
     *
     * Security:
     *   1. Group isolation check — topic must belong to user's group
     *   2. Visibility rules — excluded posts are filtered from the PDF
     *   3. Moderation — removed posts are excluded
     *   4. User must be authenticated
     */
    public function exportPDF(Topic $topic)
    {
        // Ensure user is authenticated
        if (! Auth::check()) {
            abort(403, 'You must be logged in to export topics.');
        }

        // === GROUP ISOLATION CHECK ===
        if (! Auth::user()->canAccessGroup($topic->group_id)) {
            abort(403, 'You do not have access to this topic.');
        }

        // Load visible, non-removed replies with their authors
        $replies = Post::where('topic_id', $topic->id)
            ->notRemoved()
            ->visibleToUser(Auth::id())
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        // Load the topic creator and group for the PDF
        $topic->load(['creator', 'group']);

        // Log the export for audit trail
        app(AuditLogService::class)->log(
            action: 'topic.exported',
            target: $topic,
            newValues: ['format' => 'pdf'],
            description: Auth::user()->full_name.
                ' exported topic "'.
                $topic->title.
                '" as PDF',
        );

        $pdf = Pdf::loadView('forum.export-pdf', [
            'topic' => $topic,
            'replies' => $replies,
            'exportedBy' => Auth::user(),
        ]);

        return $pdf->download('topic-'.$topic->id.'.pdf');
    }

    /**
     * ============================================
     * Task 5.2 — Share Topic via Signed URL
     * ============================================
     *
     * Generate a time-limited signed URL to share a topic with others.
     * Uses Laravel's built-in signed URL functionality to ensure security.
     *
     * Security:
     *   1. Group isolation check — topic must belong to user's group
     *   2. Visibility rules — excluded posts are filtered from the shared view
     *   3. Moderation — removed posts are excluded
     *   4. User must be authenticated
     */
    public function shareTopic(Request $request, Topic $topic)
    {
        // Ensure user is authenticated
        if (! Auth::check()) {
            abort(403, 'You must be logged in to share topics.');
        }

        // === GROUP ISOLATION CHECK ===
        if (! Auth::user()->canAccessGroup($topic->group_id)) {
            abort(403, 'You do not have access to this topic.');
        }

        // Validate request data
        $validated = $request->validate([
            'expires_in_days' => 'required|integer|min:1|max:7',
        ]);

        $expiresInDays = (int) $validated['expires_in_days'];

        // Calculate expiration time (current time + X days)
        $expires = now()->addDays($expiresInDays);

        // Generate signed URL using Laravel's built-in functionality
        $signedUrl = URL::temporarySignedRoute('shared.topic.show', $expires, [
            'topic' => $topic->id,
            'signedUserId' => Auth::id(),
        ]);

        // Log the share action for audit trail
        app(AuditLogService::class)->log(
            action: 'topic.shared',
            target: $topic,
            newValues: ['expires_in_days' => $expiresInDays],
            description: Auth::user()->full_name.
                ' shared topic "'.
                $topic->title.
                '" via signed URL',
        );

        // Return the signed URL to the view
        return back()->with('share_url', $signedUrl);
    }

    /**
     * Display the authenticated user's notifications.
     *
     * Shows paginated notifications, unread first, then by most recent.
     */
    public function notifications()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderByRaw('read_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * ============================================
     * Task 6.36 — Mark a Notification as Read (Web)
     * ============================================
     *
     * Mark a single notification as read via POST from the web UI.
     * Only the notification owner can mark it as read.
     */
    public function markNotificationAsRead(int $notificationId)
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return redirect()
            ->route('notifications')
            ->with('success', 'Notification marked as read.');
    }
}
