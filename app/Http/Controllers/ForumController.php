<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\Post;
use Illuminate\Http\Request;

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
        $topics = Topic::where('group_id', auth()->user()->group_id)
                        ->where('status', 'active')
                        ->with('creator')           // Eager load creator (avoids N+1)
                        ->withCount('posts')        // Add posts_count column
                        ->latest()
                        ->paginate(10);

        return view('forum.index', compact('topics'));
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
     * Security: group_id is taken from auth()->user() — the user cannot
     * override it via the form payload. This prevents cross-group topic creation.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|max:255|unique:topics,title',
            'description' => 'required|string|max:10000',
            'post_type'   => 'sometimes|in:discussion,question',
        ]);

        Topic::create([
            'title'       => $request->title,
            'description' => $request->description,
            'post_type'   => $request->post_type ?? 'discussion',
            'created_by'  => auth()->id(),
            'group_id'    => auth()->user()->group_id,   // Critical: scoped to user's group
            'status'      => 'active',
        ]);

        return redirect()->route('forum.index')
                         ->with('success', 'Topic created successfully!');
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
        if ($topic->group_id !== auth()->user()->group_id) {
            abort(403, 'You do not have access to this topic.');
        }

        // === NESTED EAGER LOADING ===
        // Load all replies (posts) for this topic, filtered:
        //   1. Only non-removed posts (moderation soft-delete)
        //   2. Only posts the current user is not excluded from (visibility rules)
        //   3. Order by oldest first (chronological thread)
        //   4. Eager load the author to avoid N+1
        $topic->load(['posts' => function ($query) {
            $query->notRemoved()
                  ->visibleToUser(auth()->id())
                  ->orderBy('created_at', 'asc')
                  ->with('user');
        }]);

        return view('forum.show', compact('topic'));
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
        if ($topic->group_id !== auth()->user()->group_id) {
            abort(403, 'You do not have access to this topic.');
        }

        // === STATUS CHECK — Only active topics accept replies ===
        if ($topic->status !== 'active') {
            return back()->with('error', 'This topic is closed for replies.');
        }

        $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        Post::create([
            'topic_id' => $topic->id,
            'user_id'  => auth()->id(),
            'content'  => $request->content,
        ]);

        return redirect()->route('forum.show', $topic->id)
                         ->with('success', 'Reply posted successfully!');
    }
}
